<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Services;

use Illuminate\Support\Facades\DB;
use Storix\ContainerMovement\DTOs\ReturnContainerDTO;
use Storix\ContainerMovement\Events\ContainerReturned;
use Storix\ContainerMovement\Exceptions\ContainerMovementException;
use Storix\ContainerMovement\Models\ContainerReturn;

final class ContainerReturnService
{
    public function __construct(private readonly ContainerMovementValidator $validator) {}

    public function return(ReturnContainerDTO $dto): ContainerReturn
    {
        if ($dto->items === []) {
            throw new ContainerMovementException('At least one container is required for return.');
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
