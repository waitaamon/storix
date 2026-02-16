<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Models\Dispatch;

final class ContainerAgingReportWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $openDispatches = Dispatch::query()->whereNull('return_date')->get(['dispatched_at']);

        if ($openDispatches->isEmpty()) {
            return [
                Stat::make('Open Dispatches', '0'),
                Stat::make('Average Aging (days)', '0'),
                Stat::make('Oldest Open (days)', '0'),
            ];
        }

        $today = CarbonImmutable::now();
        $ages = $openDispatches->map(fn ($dispatch): int|float => $today->diffInDays(CarbonImmutable::parse($dispatch->dispatched_at)));

        return [
            Stat::make('Open Dispatches', (string) $openDispatches->count()),
            Stat::make('Average Aging (days)', (string) round((float) $ages->avg(), 2)),
            Stat::make('Oldest Open (days)', (string) $ages->max()),
        ];
    }
}
