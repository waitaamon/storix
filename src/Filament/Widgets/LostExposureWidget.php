<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Override;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Enums\ReturnCondition;
use Storix\Models\DispatchEntry;

final class LostExposureWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $lostEntries = DispatchEntry::query()
            ->with('container')
            ->where('return_condition', ReturnCondition::Lost)
            ->whereHas('dispatch')
            ->whereHas('container')
            ->get();

        $exposure = $lostEntries->sum(fn (DispatchEntry $entry): float => (float) $entry->container->replacement_cost);

        return [
            Stat::make('Lost Containers', (string) $lostEntries->count()),
            Stat::make('Estimated Exposure', number_format($exposure, 2, '.', '')),
        ];
    }
}
