<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\DispatchEntry;
use Storix\Support\TableNames;

final class ContainerUtilizationWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        // Utilization
        $totalContainers = Container::query()->count();

        $inUseContainers = DispatchEntry::query()
            ->whereNull('return_date')
            ->whereHas('dispatch')
            ->whereHas('container')
            ->distinct('container_id')
            ->count('container_id');

        $utilization = $totalContainers === 0 ? 0 : round(($inUseContainers / $totalContainers) * 100, 2);

        // Returns & damage
        $returnedEntriesQuery = DispatchEntry::query()
            ->whereNotNull('return_date')
            ->whereHas('dispatch')
            ->whereHas('container');

        $returned = (clone $returnedEntriesQuery)->count();

        $damaged = (clone $returnedEntriesQuery)
            ->where('return_condition', ReturnCondition::Damaged)
            ->count();

        $rate = $returned > 0 ? round(($damaged / $returned) * 100, 2) : 0.0;

        $lost = DispatchEntry::query()
            ->where('return_condition', ReturnCondition::Lost)
            ->whereHas('dispatch')
            ->whereHas('container')
            ->count();

        // Aging (DB-level aggregation)
        $aging = DB::table(TableNames::dispatchEntries())
            ->join(TableNames::dispatches(), TableNames::dispatches().'.id', '=', TableNames::dispatchEntries().'.dispatch_id')
            ->whereNull(TableNames::dispatchEntries().'.return_date')
            ->whereNotNull(TableNames::dispatches().'.dispatched_at')
            ->selectRaw('COUNT(*) as open_count')
            ->selectRaw('COALESCE(ROUND(AVG(DATEDIFF(NOW(), '.TableNames::dispatches().'.dispatched_at))), 0) as avg_aging')
            ->selectRaw('COALESCE(MAX(DATEDIFF(NOW(), '.TableNames::dispatches().'.dispatched_at)), 0) as max_aging')
            ->first();

        $openCount = (int) ($aging?->open_count ?? 0);
        $avgAging = (int) ($aging?->avg_aging ?? 0);
        $oldest = (int) ($aging?->max_aging ?? 0);

        return [
            Stat::make('Total Containers', (string) $totalContainers)
                ->description("In Use: {$inUseContainers} · Utilization: ".sprintf('%.2f%%', $utilization)),

            Stat::make('Returned Dispatches', (string) $returned)
                ->description("Damaged: {$damaged} · Lost: {$lost} · Damage Rate: ".sprintf('%.2f%%', $rate)),

            Stat::make('Open Dispatches', (string) $openCount)
                ->description("Avg Aging: {$avgAging} days · Oldest: {$oldest} days"),
        ];
    }
}
