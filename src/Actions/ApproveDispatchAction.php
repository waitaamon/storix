<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Events\ContainerDispatched;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Models\States\DispatchApprovedState;
use Storix\Models\States\DispatchDraftState;
use Throwable;

final class ApproveDispatchAction
{
    /**
     * @throws Throwable
     */
    public function handle(Dispatch $dispatch, int|string|null $approvedBy = null): Dispatch
    {
        return DB::transaction(function () use ($dispatch, $approvedBy): Dispatch {
            $dispatch = Dispatch::query()
                ->whereKey($dispatch->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $dispatch->state->equals(DispatchDraftState::class)) {
                throw new DomainException('Only draft dispatches can be approved.');
            }

            $entries = DispatchEntry::query()
                ->with('container')
                ->where('dispatch_id', $dispatch->getKey())
                ->lockForUpdate()
                ->get();

            if ($entries->isEmpty()) {
                throw new DomainException('A dispatch cannot be approved without containers.');
            }

            foreach ($entries as $entry) {
                if (! $entry->container->is_active) {
                    throw new DomainException('All containers must be active before approval.');
                }

                $conflictingEntry = DispatchEntry::query()
                    ->where('container_id', $entry->container_id)
                    ->whereNull('return_date')
                    ->whereKeyNot($entry->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($conflictingEntry) {
                    throw new DomainException("Container [{$entry->container->serial}] is already reserved or dispatched.");
                }
            }

            $dispatch->forceFill([
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            $dispatch->state->transitionTo(DispatchApprovedState::class);
            $dispatch = $dispatch->refresh();

            foreach ($entries as $entry) {
                ContainerDispatched::dispatch($entry->refresh());
            }

            return $dispatch;
        });
    }
}
