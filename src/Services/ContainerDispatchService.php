<?php

declare(strict_types=1);

namespace Storix\Services;

use Illuminate\Support\Facades\DB;
use Storix\DTOs\DispatchContainerDTO;
use Storix\Events\ContainerDispatched;
use Storix\Exceptions\StorixException;
use Storix\Models\ContainerDispatch;

final class ContainerDispatchService
{
    public function __construct(private readonly StorixValidator $validator) {}

    /**
     * Dispatch one or more containers to a customer.
     *
     * @throws StorixException
     */
    public function dispatch(DispatchContainerDTO $dto): ContainerDispatch
    {
        if ($dto->containerSerials === []) {
            throw new StorixException('At least one container serial is required for dispatch.');
        }

        /** @var ContainerDispatch $dispatch */
        $dispatch = DB::transaction(function () use ($dto): ContainerDispatch {
            $dispatch = ContainerDispatch::query()->create([
                'customer_id' => $dto->customerId,
                'sale_order_code' => $dto->saleOrderCode,
                'transaction_date' => $dto->transactionDate->toDateString(),
                'user_id' => $dto->userId,
                'notes' => $dto->notes,
                'attachments' => $dto->attachments,
            ]);

            foreach ($dto->containerSerials as $serial) {
                $container = $this->validator->resolveContainerBySerial($serial);
                $this->validator->assertDispatchable($container);

                $dispatch->items()->create([
                    'container_id' => $container->getKey(),
                ]);
            }

            return $dispatch->fresh(['items.container']) ?? $dispatch;
        });

        ContainerDispatched::dispatch($dispatch);

        return $dispatch;
    }
}
