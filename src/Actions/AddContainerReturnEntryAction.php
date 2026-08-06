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

final readonly class AddContainerReturnEntryAction
{
    use NotifiesFilamentOfExceptions;

    public function __construct(
        private ContainerReturnEntryGuard $guard,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(
        ContainerReturn $containerReturn,
        AddContainerReturnEntryData $data,
    ): ContainerReturnEntry {
        try {
            return DB::transaction(function () use ($containerReturn, $data): ContainerReturnEntry {
                $containerReturn = ContainerReturn::query()
                    ->whereKey($containerReturn->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $container = Container::query()
                    ->whereKey($data->containerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->guard->ensureCanStore($containerReturn, $container);

                return ContainerReturnEntry::query()->create([
                    'container_return_id' => $containerReturn->getKey(),
                    'container_id' => $container->getKey(),
                    'return_condition' => $data->returnCondition(),
                    'note' => $data->note,
                ]);
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
