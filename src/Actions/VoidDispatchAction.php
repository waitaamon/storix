<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Data\VoidDispatchData;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Models\States\ContainerReturnApprovedState;
use Storix\Models\States\DispatchVoidedState;
use Throwable;

final class VoidDispatchAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(Dispatch $dispatch, VoidDispatchData $data): Dispatch
    {
        try {
            return DB::transaction(function () use ($dispatch, $data): Dispatch {
                $dispatch = Dispatch::query()
                    ->whereKey($dispatch->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($dispatch->state->equals(DispatchVoidedState::class)) {
                    throw new DomainException('This dispatch is already voided.');
                }

                if (blank($data->reason)) {
                    throw new DomainException('A void reason is required.');
                }

                $entries = DispatchEntry::query()
                    ->where('dispatch_id', $dispatch->getKey())
                    ->lockForUpdate()
                    ->get();

                $hasPostedReturns = ContainerReturnEntry::query()
                    ->whereIn('dispatch_entry_id', $entries->modelKeys())
                    ->whereHas(
                        'containerReturn',
                        fn ($query) => $query->whereState('state', ContainerReturnApprovedState::class),
                    )
                    ->exists();

                if ($hasPostedReturns) {
                    throw new DomainException('Dispatches with return activity require a reversal workflow instead of voiding.');
                }

                $dispatch->forceFill([
                    'voided_by' => $data->voidedBy,
                    'voided_at' => now(),
                    'void_reason' => $data->reason,
                ]);

                $dispatch->state->transitionTo(DispatchVoidedState::class);

                foreach ($entries as $entry) {
                    $entry->delete();
                }

                return $dispatch->refresh();
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
