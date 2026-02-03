<?php

declare(strict_types=1);

namespace Storix\Services;

use Illuminate\Support\Facades\DB;
use Storix\DTOs\ReturnContainerDTO;
use Storix\Events\ContainerReturned;
use Storix\Exceptions\StorixException;
use Storix\Models\ContainerReturn;

final class ContainerReturnService
{
    public function __construct(private readonly StorixValidator $validator) {}

    /**
     * Record the return of one or more containers from a customer.
     *
     * @throws StorixException
     */
    public function return(ReturnContainerDTO $dto): ContainerReturn
    {
        if ($dto->items === []) {
            throw new StorixException('At least one container is required for return.');
        }

        /** @var ContainerReturn $return */
        $return = DB::transaction(function () use ($dto): ContainerReturn {
            $return = ContainerReturn::query()->create([
                'customer_id' => $dto->customerId,
                'transaction_date' => $dto->transactionDate->toDateString(),
                'user_id' => $dto->userId,
                'notes' => $dto->notes,
                'attachments' => $dto->attachments,
            ]);

            foreach ($dto->items as $item) {
                $container = $this->validator->resolveContainerBySerial($item->containerSerial);
                $openDispatchItem = $this->validator->resolveOpenDispatchItem($container, $dto->customerId);

                $return->items()->create([
                    'container_id' => $container->getKey(),
                    'dispatch_item_id' => $openDispatchItem->getKey(),
                    'condition_status' => $item->conditionStatus->value,
                    'notes' => $item->notes,
                    'returned_at' => $dto->transactionDate,
                ]);
            }

            return $return->fresh(['items.container', 'items.dispatchItem.dispatch']) ?? $return;
        });

        ContainerReturned::dispatch($return);

        return $return;
    }
}
