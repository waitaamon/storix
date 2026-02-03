<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Storix\ContainerMovement\Models\ContainerDispatchItem;

final class OverdueReturnsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $overdueAfterDays = (int) config('container-movement.overdue_after_days', 30);
        $cutoff = now()->subDays($overdueAfterDays)->toDateString();

        $count = ContainerDispatchItem::query()
            ->open()
            ->whereHas('dispatch', static fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $cutoff))
            ->count();

        return [
            Stat::make('Overdue Returns', number_format($count))
                ->description(sprintf('Open for more than %d day(s)', $overdueAfterDays))
                ->color($count > 0 ? 'danger' : 'success'),
        ];
    }
}
