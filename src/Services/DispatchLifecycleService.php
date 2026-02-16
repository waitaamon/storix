<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use WaitAmon\Storix\Data\DispatchLifecycleData;
use WaitAmon\Storix\Enums\ReturnCondition;
use WaitAmon\Storix\Models\Dispatch;

final class DispatchLifecycleService
{
    public function createDispatch(DispatchLifecycleData $data): Dispatch
    {
        return DB::transaction(function () use ($data): Dispatch {
            return Dispatch::query()->create([
                'container_id' => $data->containerId,
                'customer_id' => $data->customerId,
                'dispatched_by' => $data->dispatchedBy,
                'delivery_note' => $data->deliveryNote,
                'dispatched_note' => $data->dispatchedNote,
                'dispatched_at' => $data->dispatchedAt ?? CarbonImmutable::now(),
            ]);
        });
    }

    public function registerReturn(
        Dispatch $dispatch,
        ReturnCondition $condition,
        string $receivedBy,
        ?string $note = null,
        ?CarbonImmutable $returnedAt = null,
    ): Dispatch {
        return DB::transaction(function () use ($dispatch, $condition, $receivedBy, $note, $returnedAt): Dispatch {
            $dispatch->forceFill([
                'received_by' => $receivedBy,
                'return_date' => $returnedAt ?? CarbonImmutable::now(),
                'return_condition' => $condition,
                'return_note' => $note,
            ]);

            $dispatch->save();

            return $dispatch->refresh();
        });
    }
}
