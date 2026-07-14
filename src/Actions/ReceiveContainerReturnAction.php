<?php

declare(strict_types=1);

namespace Storix\Actions;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Data\ReceiveContainerReturnData;
use Storix\Enums\ReturnCondition;
use Storix\Events\ContainerDamaged;
use Storix\Events\ContainerLost;
use Storix\Events\ContainerReturned;
use Storix\Models\DispatchEntry;
use Storix\Models\States\DispatchApprovedState;
use Throwable;

final class ReceiveContainerReturnAction
{
    /**
     * @throws Throwable
     */
    public function handle(DispatchEntry $entry, ReceiveContainerReturnData $data): DispatchEntry
    {
        return DB::transaction(function () use ($entry, $data): DispatchEntry {
            $entry = DispatchEntry::query()
                ->with(['container', 'dispatch'])
                ->whereKey($entry->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $entry->dispatch->state->equals(DispatchApprovedState::class)) {
                throw new DomainException('Only containers from approved dispatches can be received.');
            }

            if ($entry->return_date !== null) {
                throw new DomainException('This container has already been received.');
            }

            $returnDate = CarbonImmutable::parse($data->returnDate);
            $dispatchDate = $entry->dispatch->dispatched_at;

            if ($dispatchDate && $returnDate->lt($dispatchDate)) {
                throw new DomainException('Return date cannot be earlier than dispatch date.');
            }

            $condition = $data->condition instanceof ReturnCondition
                ? $data->condition
                : ReturnCondition::from($data->condition);

            $entry->forceFill([
                'received_by' => $data->receivedBy ?? (auth()->check() ? auth()->id() : null),
                'return_date' => $returnDate,
                'return_condition' => $condition,
                'return_note' => $data->note,
            ])->save();

            match ($condition) {
                ReturnCondition::Good => ContainerReturned::dispatch($entry),
                ReturnCondition::Damaged => $this->dispatchDamagedEvents($entry),
                ReturnCondition::Lost => $this->markLost($entry),
            };

            return $entry->refresh();
        });
    }

    private function dispatchDamagedEvents(DispatchEntry $entry): null
    {
        ContainerReturned::dispatch($entry);
        ContainerDamaged::dispatch($entry);

        return null;
    }

    private function markLost(DispatchEntry $entry): null
    {
        $entry->container->forceFill(['is_active' => false])->save();
        ContainerLost::dispatch($entry);

        return null;
    }
}
