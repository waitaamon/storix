<?php

declare(strict_types=1);

namespace Storix\Actions;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Models\States\DispatchApprovedState;
use Storix\Models\States\DispatchDraftState;
use Storix\Support\OutstandingDispatchEntryQuery;
use Throwable;

final class AttachContainersToDispatchAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @param  list<int|string>  $containerIds
     *
     * @throws Throwable
     */
    public function handle(Dispatch $dispatch, array $containerIds, bool $checkAvailability = true): void
    {
        try {
            DB::transaction(function () use ($dispatch, $containerIds, $checkAvailability): void {

                $dispatch = Dispatch::query()->whereKey($dispatch->getKey())->lockForUpdate()->firstOrFail();

                if (! $dispatch->state->equals(DispatchDraftState::class)) {
                    throw new DomainException('Containers can only be attached to draft dispatches.');
                }

                foreach ($this->normalizeIds($containerIds) as $containerId) {
                    $container = Container::query()->whereKey($containerId)->lockForUpdate()->firstOrFail();

                    if (! $container->is_active) {
                        throw new DomainException("Container [{$container->serial}] is inactive.");
                    }

                    if ($checkAvailability) {

                        $reservedEntry = DispatchEntry::query()
                            ->where('container_id', $container->getKey())
                            ->whereHas(
                                'dispatch',
                                fn (Builder $query): Builder => $query->whereState('state', DispatchDraftState::class),
                            )
                            ->lockForUpdate()
                            ->first();

                        if ($reservedEntry && (string) $reservedEntry->dispatch_id !== (string) $dispatch->getKey()) {
                            throw new DomainException("Container [{$container->serial}] is already reserved or dispatched.");
                        }

                        $outstandingEntry = OutstandingDispatchEntryQuery::forContainer($container->getKey())
                            ->whereHas(
                                'dispatch',
                                fn (Builder $query): Builder => $query->whereState('state', DispatchApprovedState::class),
                            )
                            ->lockForUpdate()
                            ->first();

                        if ($outstandingEntry) {
                            throw new DomainException("Container [{$container->serial}] is already reserved or dispatched.");
                        }
                    }

                    DispatchEntry::query()->firstOrCreate([
                        'dispatch_id' => $dispatch->getKey(),
                        'container_id' => $container->getKey(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int|string>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter($ids, filled(...))));
    }
}
