<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Models\Container;
use Storix\Models\DispatchEntry;

final class ContainerUtilizationWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $totalContainers = Container::query()->count();

        $inUseContainers = DispatchEntry::query()
            ->whereNull('return_date')
            ->whereHas('dispatch')
            ->whereHas('container')
            ->distinct('container_id')
            ->count('container_id');

        $utilization = $totalContainers === 0 ? 0 : round(($inUseContainers / $totalContainers) * 100, 2);

        return [
            Stat::make('Total Containers', (string) $totalContainers),
            Stat::make('Containers In Use', (string) $inUseContainers),
            Stat::make('Utilization %', sprintf('%.2f%%', $utilization)),
        ];
    }
}
