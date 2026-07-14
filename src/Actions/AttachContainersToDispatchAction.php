<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Models\States\DispatchDraftState;
use Throwable;

final class AttachContainersToDispatchAction
{
    /**
     * @param  list<int|string>  $containerIds
     *
     * @throws Throwable
     */
    public function handle(Dispatch $dispatch, array $containerIds): void
    {
        DB::transaction(function () use ($dispatch, $containerIds): void {
            $dispatch = Dispatch::query()
                ->whereKey($dispatch->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $dispatch->state->equals(DispatchDraftState::class)) {
                throw new DomainException('Containers can only be attached to draft dispatches.');
            }

            foreach ($this->normalizeIds($containerIds) as $containerId) {
                $container = Container::query()
                    ->whereKey($containerId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $container->is_active) {
                    throw new DomainException("Container [{$container->serial}] is inactive.");
                }

                $openEntry = DispatchEntry::query()
                    ->where('container_id', $container->getKey())
                    ->whereNull('return_date')
                    ->lockForUpdate()
                    ->first();

                if ($openEntry && (string) $openEntry->dispatch_id !== (string) $dispatch->getKey()) {
                    throw new DomainException("Container [{$container->serial}] is already reserved or dispatched.");
                }

                DispatchEntry::query()->firstOrCreate([
                    'dispatch_id' => $dispatch->getKey(),
                    'container_id' => $container->getKey(),
                ]);
            }
        });
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int|string>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            $ids,
            filled(...),
        )));
    }
}
