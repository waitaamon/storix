<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Storix\Models\ContainerDispatch;
use Storix\Models\ContainerReturn;

final class DispatchReturnTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Dispatch vs Return Trend (14 days)';

    protected int|string|array $columnSpan = 'full';

    /** Build the 14-day dispatch vs return trend chart data. */
    protected function getData(): array
    {
        $start = CarbonImmutable::now()->subDays(13)->startOfDay();
        $end = CarbonImmutable::now()->endOfDay();

        $dateKeys = [];
        $labels = [];

        for ($day = 13; $day >= 0; $day--) {
            $date = CarbonImmutable::now()->subDays($day);
            $dateKeys[] = $date->toDateString();
            $labels[] = $date->format('M j');
        }

        $dispatchCounts = ContainerDispatch::query()
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('transaction_date, count(*) as aggregate')
            ->groupBy('transaction_date')
            ->pluck('aggregate', 'transaction_date');

        $returnCounts = ContainerReturn::query()
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('transaction_date, count(*) as aggregate')
            ->groupBy('transaction_date')
            ->pluck('aggregate', 'transaction_date');

        return [
            'datasets' => [
                [
                    'label' => 'Dispatches',
                    'data' => array_map(static fn (string $date): int => (int) ($dispatchCounts[$date] ?? 0), $dateKeys),
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => '#0ea5e933',
                ],
                [
                    'label' => 'Returns',
                    'data' => array_map(static fn (string $date): int => (int) ($returnCounts[$date] ?? 0), $dateKeys),
                    'borderColor' => '#16a34a',
                    'backgroundColor' => '#16a34a33',
                ],
            ],
            'labels' => $labels,
        ];
    }

    /** @return 'line' */
    protected function getType(): string
    {
        return 'line';
    }
}
