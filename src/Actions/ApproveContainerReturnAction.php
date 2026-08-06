<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Enums\ReturnCondition;
use Storix\Events\ContainerDamaged;
use Storix\Events\ContainerLost;
use Storix\Events\ContainerReturnApproved;
use Storix\Events\ContainerReturned;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\States\ContainerReturnApprovedState;
use Storix\Models\States\ContainerReturnSubmittedState;
use Storix\Support\OutstandingDispatchEntryQuery;
use Throwable;

final class ApproveContainerReturnAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(
        ContainerReturn $containerReturn,
        int|string $approvedBy,
        bool $checkForOutstandingEntries = true,
    ): ContainerReturn {
        try {
            return DB::transaction(function () use ($containerReturn, $approvedBy, $checkForOutstandingEntries): ContainerReturn {

                $containerReturn = ContainerReturn::query()
                    ->whereKey($containerReturn->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $containerReturn->state->equals(ContainerReturnSubmittedState::class)) {
                    throw new DomainException('Only submitted container returns can be approved.');
                }

                if ((string) $containerReturn->user_id === (string) $approvedBy) {
                    throw new DomainException('The preparer cannot approve their own container return.');
                }

                $entries = ContainerReturnEntry::query()
                    ->with('container')
                    ->where('container_return_id', $containerReturn->getKey())
                    ->lockForUpdate()
                    ->get();

                if ($entries->isEmpty()) {
                    throw new DomainException('A container return cannot be approved without entries.');
                }

                foreach ($entries as $entry) {
                    $this->reconcileEntry($containerReturn, $entry, $checkForOutstandingEntries);
                }

                $containerReturn->forceFill([
                    'approved_by' => $approvedBy,
                    'approved_at' => now(),
                ]);

                $containerReturn->state->transitionTo(ContainerReturnApprovedState::class);

                $containerReturn = $containerReturn->refresh();

                ContainerReturnApproved::dispatch($containerReturn);

                return $containerReturn;
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    private function reconcileEntry(
        ContainerReturn $containerReturn,
        ContainerReturnEntry $entry,
        bool $checkForOutstandingEntries,
    ): void {
        $container = Container::query()
            ->whereKey($entry->container_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($checkForOutstandingEntries) {
            $outstandingEntries = OutstandingDispatchEntryQuery::forContainer($container->getKey())
                ->with('dispatch')
                ->lockForUpdate()
                ->get();

            if ($outstandingEntries->isEmpty()) {
                throw new DomainException("Container [{$container->serial}] has no outstanding approved dispatch.");
            }

            //            if ($outstandingEntries->count() !== 1) {
            //                throw new DomainException("Container [{$container->serial}] has multiple outstanding dispatch entries and requires reconciliation.");
            //            }

            $dispatchEntry = $outstandingEntries->first();

            $dispatchDate = $dispatchEntry->dispatch->dispatched_at;

            if ($dispatchDate !== null && $containerReturn->transaction_date->toDateString() < $dispatchDate->toDateString()) {
                throw new DomainException("Return date for container [{$container->serial}] cannot be earlier than its dispatch date.");
            }

            $entry->forceFill([
                'dispatch_entry_id' => $dispatchEntry->getKey(),
                'cross_return' => (string) $containerReturn->customer_id !== (string) $dispatchEntry->dispatch->customer_id,
            ])->save();
        }

        match ($entry->return_condition) {
            ReturnCondition::Good => ContainerReturned::dispatch($entry),
            ReturnCondition::Damaged => $this->dispatchDamagedEvents($entry),
            ReturnCondition::Lost => $this->markLost($container, $entry),
        };
    }

    private function dispatchDamagedEvents(ContainerReturnEntry $entry): null
    {
        ContainerReturned::dispatch($entry);
        ContainerDamaged::dispatch($entry);

        return null;
    }

    private function markLost(Container $container, ContainerReturnEntry $entry): null
    {
        $container->forceFill(['is_active' => false])->save();
        ContainerLost::dispatch($entry);

        return null;
    }
}
