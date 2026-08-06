<?php

declare(strict_types=1);

namespace Storix\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Data\CreateContainerReturnData;
use Storix\Models\ContainerReturn;
use Throwable;

final class CreateContainerReturnAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(CreateContainerReturnData $data): ContainerReturn
    {
        try {
            return DB::transaction(fn (): ContainerReturn => ContainerReturn::query()->create([
                'customer_id' => $data->customerId,
                'user_id' => $data->userId,
                'transaction_date' => CarbonImmutable::parse($data->transactionDate)->startOfDay(),
                'note' => $data->note,
            ]));
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
