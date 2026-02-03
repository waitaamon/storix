<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Storix\ContainerMovement\Models\ContainerDispatch;
use Storix\ContainerMovement\Models\ContainerReturn;

final class DispatchReturnTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Dispatch vs Return Trend (14 days)';

    protected int|string|array $columnSpan = 'full';

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
            ->get(['transaction_date'])
            ->countBy(static fn (ContainerDispatch $dispatch): string => $dispatch->transaction_date->toDateString());

        $returnCounts = ContainerReturn::query()
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->get(['transaction_date'])
            ->countBy(static fn (ContainerReturn $return): string => $return->transaction_date->toDateString());

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

    protected function getType(): string
    {
        return 'line';
    }
}
