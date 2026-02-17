<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Models\DispatchEntry;

final class ContainerAgingReportWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $openEntries = DispatchEntry::query()
            ->whereNull('return_date')
            ->whereHas('dispatch')
            ->with(['dispatch:id,dispatched_at'])
            ->get(['dispatch_id']);

        if ($openEntries->isEmpty()) {
            return [
                Stat::make('Open Dispatches', '0'),
                Stat::make('Average Aging (days)', '0'),
                Stat::make('Oldest Open (days)', '0'),
            ];
        }

        $today = CarbonImmutable::now();
        $ages = $openEntries->map(function (DispatchEntry $entry) use ($today): int|float {
            $dispatchedAt = $entry->dispatch?->dispatched_at;

            if (! $dispatchedAt instanceof CarbonImmutable) {
                return 0;
            }

            return $dispatchedAt->diffInDays($today);
        });

        return [
            Stat::make('Open Dispatches', (string) $openEntries->count()),
            Stat::make('Average Aging (days)', (string) round((float) $ages->avg(), 2)),
            Stat::make('Oldest Open (days)', (string) $ages->max()),
        ];
    }
}
