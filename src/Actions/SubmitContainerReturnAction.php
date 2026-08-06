<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Events\ContainerReturnSubmitted;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\States\ContainerReturnDraftState;
use Storix\Models\States\ContainerReturnSubmittedState;
use Throwable;

final class SubmitContainerReturnAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(
        ContainerReturn $containerReturn,
        bool $checkForDuplicateSubmittedEntries = true,
    ): ContainerReturn {
        try {
            return DB::transaction(function () use ($containerReturn, $checkForDuplicateSubmittedEntries): ContainerReturn {
                $containerReturn = ContainerReturn::query()
                    ->whereKey($containerReturn->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $containerReturn->state->equals(ContainerReturnDraftState::class)) {
                    throw new DomainException('Only draft container returns can be submitted.');
                }

                $entries = ContainerReturnEntry::query()
                    ->where('container_return_id', $containerReturn->getKey())
                    ->lockForUpdate()
                    ->get();

                if ($entries->isEmpty()) {
                    throw new DomainException('A container return cannot be submitted without entries.');
                }

                if ($checkForDuplicateSubmittedEntries) {
                    $duplicateSubmittedEntry = ContainerReturnEntry::query()
                        ->whereIn('container_id', $entries->pluck('container_id'))
                        ->where('container_return_id', '!=', $containerReturn->getKey())
                        ->whereHas(
                            'containerReturn',
                            fn (Builder $query): Builder => $query->whereState(
                                'state',
                                ContainerReturnSubmittedState::class,
                            ),
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($duplicateSubmittedEntry !== null) {
                        throw new DomainException('One or more containers are already included in another submitted return.');
                    }
                }

                $containerReturn->state->transitionTo(ContainerReturnSubmittedState::class);

                $containerReturn = $containerReturn->refresh();

                ContainerReturnSubmitted::dispatch($containerReturn);

                return $containerReturn;
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
