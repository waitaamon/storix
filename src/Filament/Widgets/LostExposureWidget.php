<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use WaitAmon\Storix\Models\Dispatch;

final class LostExposureWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $lostCount = Dispatch::query()->where('return_condition', 'lost')->count();

        $estimatedExposure = Dispatch::query()
            ->where('return_condition', 'lost')
            ->get(['metadata'])
            ->sum(static fn ($dispatch): float => (float) data_get($dispatch->metadata, 'replacement_cost', 0));

        return [
            Stat::make('Lost Containers', (string) $lostCount),
            Stat::make('Estimated Exposure', sprintf('%.2f', $estimatedExposure))
                ->description('Based on dispatch metadata.replacement_cost'),
        ];
    }
}
