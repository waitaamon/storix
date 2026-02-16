<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Enums\ReturnCondition;
use Storix\Models\Dispatch;

final class LostExposureWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $lostCount = Dispatch::query()->where('return_condition', ReturnCondition::Lost)->count();

        $estimatedExposure = Dispatch::query()
            ->where('return_condition', ReturnCondition::Lost)
            ->get(['metadata'])
            ->sum(static fn ($dispatch): float => (float) data_get($dispatch->metadata, 'replacement_cost', 0));

        return [
            Stat::make('Lost Containers', (string) $lostCount),
            Stat::make('Estimated Exposure', sprintf('%.2f', $estimatedExposure))
                ->description('Based on dispatch metadata.replacement_cost'),
        ];
    }
}
