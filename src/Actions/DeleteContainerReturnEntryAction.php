<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\States\ContainerReturnDraftState;
use Throwable;

final class DeleteContainerReturnEntryAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(ContainerReturnEntry $entry): void
    {
        try {
            DB::transaction(function () use ($entry): void {
                $entry = ContainerReturnEntry::query()
                    ->whereKey($entry->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $containerReturn = ContainerReturn::query()
                    ->whereKey($entry->container_return_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $containerReturn->state->equals(ContainerReturnDraftState::class)) {
                    throw new DomainException('Entries can only be changed on draft container returns.');
                }

                $entry->delete();
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
