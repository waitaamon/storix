<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Models\ContainerReturn;
use Storix\Models\States\ContainerReturnDraftState;
use Storix\Models\States\ContainerReturnSubmittedState;
use Throwable;

final class ReturnContainerReturnToDraftAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(ContainerReturn $containerReturn): ContainerReturn
    {
        try {
            return DB::transaction(function () use ($containerReturn): ContainerReturn {
                $containerReturn = ContainerReturn::query()
                    ->whereKey($containerReturn->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $containerReturn->state->equals(ContainerReturnSubmittedState::class)) {
                    throw new DomainException('Only submitted container returns can be returned to draft.');
                }

                $containerReturn->state->transitionTo(ContainerReturnDraftState::class);

                return $containerReturn->refresh();
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
