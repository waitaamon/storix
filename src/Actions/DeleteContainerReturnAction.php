<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Models\ContainerReturn;
use Storix\Models\States\ContainerReturnDraftState;
use Throwable;

final class DeleteContainerReturnAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(ContainerReturn $containerReturn): void
    {
        try {
            DB::transaction(function () use ($containerReturn): void {
                $containerReturn = ContainerReturn::query()
                    ->whereKey($containerReturn->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $containerReturn->state->equals(ContainerReturnDraftState::class)) {
                    throw new DomainException('Only draft container returns can be deleted.');
                }

                $containerReturn->delete();
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
