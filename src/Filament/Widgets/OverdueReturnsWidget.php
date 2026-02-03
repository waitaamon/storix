<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Storix\Models\ContainerDispatchItem;

final class OverdueReturnsWidget extends StatsOverviewWidget
{
    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $overdueAfterDays = (int) config('storix.overdue_after_days', 30);
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
