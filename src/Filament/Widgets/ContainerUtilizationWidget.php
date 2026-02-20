<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\DispatchEntry;

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

        // Aging
        $openEntries = DispatchEntry::query()
            ->whereNull('return_date')
            ->whereHas('dispatch')
            ->with(['dispatch:id,dispatched_at'])
            ->get(['dispatch_id']);
        $today = CarbonImmutable::now();
        $ages = $openEntries->map(function (DispatchEntry $entry) use ($today): int|float {
            $dispatchedAt = $entry->dispatch?->dispatched_at;

            if (! $dispatchedAt instanceof CarbonImmutable) {
                return 0;
            }

            return $dispatchedAt->diffInDays($today);
        });
        $avgAging = $openEntries->isNotEmpty() ? (int) round((float) $ages->avg()) : 0;
        $oldest = $openEntries->isNotEmpty() ? (int) $ages->max() : 0;

        return [
            Stat::make('Total Containers', (string) $totalContainers)
                ->description("In Use: {$inUseContainers} · Utilization: ".sprintf('%.2f%%', $utilization)),

            Stat::make('Returned Dispatches', (string) $returned)
                ->description(new HtmlString("Damaged: $damaged · Lost: $lost · Damage Rate: ".sprintf('%.2f%%', $rate))),

            Stat::make('Open Dispatches', (string) $openEntries->count())
                ->description("Avg Aging: $avgAging days · Oldest: $oldest days"),
        ];
    }
}
