<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Data\AddContainerReturnEntryBySerialData;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Support\ContainerReturnEntryGuard;
use Storix\Support\OutstandingDispatchEntryQuery;

final readonly class AddContainerReturnEntryBySerialAction
{
    public function __construct(
        private ContainerReturnEntryGuard $guard,
    ) {}

    public function handle(
        ContainerReturn $containerReturn,
        AddContainerReturnEntryBySerialData $data,
    ): ContainerReturnEntry {
        return DB::transaction(function () use ($containerReturn, $data): ContainerReturnEntry {
            $containerReturn = ContainerReturn::query()
                ->whereKey($containerReturn->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $serial = $data->normalizedSerial();
            $container = Container::query()
                ->where('serial', $serial)
                ->lockForUpdate()
                ->first();

            if ($container === null) {
                throw new DomainException("No container found with serial [{$serial}].");
            }

            $this->guard->ensureCanStore($containerReturn, $container);

            if (OutstandingDispatchEntryQuery::forContainer($container->getKey())
                ->lockForUpdate()
                ->doesntExist()) {
                throw new DomainException(
                    "Container [{$container->serial}] has no outstanding approved dispatch.",
                );
            }

            $entryData = $data->entryData($container->getKey());

            return ContainerReturnEntry::query()->create([
                'container_return_id' => $containerReturn->getKey(),
                'container_id' => $container->getKey(),
                'return_condition' => $entryData->returnCondition(),
                'note' => $entryData->note,
            ]);
        });
    }
}
