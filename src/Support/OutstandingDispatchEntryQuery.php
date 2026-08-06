<?php

declare(strict_types=1);

namespace Storix\Support;

use Illuminate\Database\Eloquent\Builder;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Models\States\ContainerReturnApprovedState;
use Storix\Models\States\DispatchApprovedState;

final class OutstandingDispatchEntryQuery
{
    /**
     * @return Builder<DispatchEntry>
     */
    public static function forContainer(int|string $containerId): Builder
    {
        return self::query()->where('container_id', $containerId);
    }

    /**
     * @return Builder<DispatchEntry>
     */
    public static function query(): Builder
    {
        $dispatchTable = TableNames::dispatches();
        $dispatchEntryTable = TableNames::dispatchEntries();

        return DispatchEntry::query()
            ->whereHas(
                'dispatch',
                fn (Builder $query): Builder => $query->whereState(
                    'state',
                    DispatchApprovedState::class,
                ),
            )
            ->whereDoesntHave(
                'containerReturnEntry',
                fn (Builder $query): Builder => $query->whereHas(
                    'containerReturn',
                    fn (Builder $query): Builder => $query->whereState(
                        'state',
                        ContainerReturnApprovedState::class,
                    ),
                ),
            )
            ->orderByDesc(
                Dispatch::query()
                    ->select('approved_at')
                    ->whereColumn("{$dispatchTable}.id", "{$dispatchEntryTable}.dispatch_id")
                    ->limit(1),
            )
            ->orderByDesc("{$dispatchEntryTable}.id");
    }
}
