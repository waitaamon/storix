<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Enums\ReturnCondition;
use Storix\Models\DispatchEntry;

final class LostExposureWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $lostEntries = DispatchEntry::query()
            ->where('return_condition', ReturnCondition::Lost)
            ->whereHas('dispatch')
            ->whereHas('container')
            ->with(['container:id,metadata'])
            ->get(['container_id']);

        $lostCount = $lostEntries->count();
        $estimatedExposure = $lostEntries->sum(
            static fn (DispatchEntry $entry): float => (float) data_get($entry->container?->metadata, 'replacement_cost', 0),
        );

        return [
            Stat::make('Lost Containers', (string) $lostCount),
            Stat::make('Estimated Exposure', sprintf('%.2f', $estimatedExposure))
                ->description('Based on container metadata.replacement_cost'),
        ];
    }
}
