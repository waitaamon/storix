<?php

declare(strict_types=1);

namespace Storix\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Data\UpdateContainerReturnData;
use Storix\Models\ContainerReturn;
use Storix\Models\States\ContainerReturnDraftState;
use Throwable;

final class UpdateContainerReturnAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(
        ContainerReturn $containerReturn,
        UpdateContainerReturnData $data,
    ): ContainerReturn {
        try {
            return DB::transaction(function () use ($containerReturn, $data): ContainerReturn {
                $containerReturn = ContainerReturn::query()
                    ->whereKey($containerReturn->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $containerReturn->state->equals(ContainerReturnDraftState::class)) {
                    throw new DomainException('Only draft container returns can be updated.');
                }

                $containerReturn->update([
                    'customer_id' => $data->customerId,
                    'transaction_date' => CarbonImmutable::parse($data->transactionDate)->startOfDay(),
                    'note' => $data->note,
                ]);

                return $containerReturn->refresh();
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
