<?php

declare(strict_types=1);

namespace Storix\Services;

use Carbon\CarbonImmutable;
use Storix\Data\DispatchLifecycleData;
use Storix\Enums\ReturnCondition;
use Storix\Models\Dispatch;

final class DispatchLifecycleService
{
    public function createDispatch(DispatchLifecycleData $data): Dispatch
    {
        return Dispatch::query()->create([
            'container_id' => $data->containerId,
            'customer_id' => $data->customerId,
            'dispatched_by' => $data->dispatchedBy,
            'delivery_note' => $data->deliveryNote,
            'dispatched_note' => $data->dispatchedNote,
            'dispatched_at' => $data->dispatchedAt ?? CarbonImmutable::now(),
        ]);
    }

    public function registerReturn(
        Dispatch $dispatch,
        ReturnCondition $condition,
        int $receivedBy,
        ?string $note = null,
        ?CarbonImmutable $returnedAt = null,
    ): Dispatch {
        $dispatch->update([
            'received_by' => $receivedBy,
            'return_date' => $returnedAt ?? CarbonImmutable::now(),
            'return_condition' => $condition,
            'return_note' => $note,
        ]);

        return $dispatch->refresh();
    }
}
