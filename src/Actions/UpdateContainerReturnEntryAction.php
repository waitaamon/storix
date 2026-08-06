<?php

declare(strict_types=1);

namespace Storix\Actions;

use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Data\AddContainerReturnEntryData;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Support\ContainerReturnEntryGuard;
use Throwable;

final readonly class UpdateContainerReturnEntryAction
{
    use NotifiesFilamentOfExceptions;

    public function __construct(
        private ContainerReturnEntryGuard $guard,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(
        ContainerReturnEntry $entry,
        AddContainerReturnEntryData $data,
    ): ContainerReturnEntry {
        try {
            return DB::transaction(function () use ($entry, $data): ContainerReturnEntry {
                $entry = ContainerReturnEntry::query()
                    ->whereKey($entry->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $containerReturn = ContainerReturn::query()
                    ->whereKey($entry->container_return_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $container = Container::query()
                    ->whereKey($data->containerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->guard->ensureCanStore($containerReturn, $container, $entry);

                $entry->update([
                    'container_id' => $container->getKey(),
                    'return_condition' => $data->returnCondition(),
                    'note' => $data->note,
                ]);

                return $entry->refresh();
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
