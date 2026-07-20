<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Override;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\DispatchEntry;
use Storix\Models\States\DispatchApprovedState;

final class ContainerFleetOverviewWidget extends StatsOverviewWidget
{
    #[Override]
    protected ?string $pollingInterval = '60s';

    /** @var array<string, int> */
    #[Override]
    protected int|array|null $columns = [
        'md' => 2,
        'xl' => 3,
    ];

    /**
     * @return array<int, Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $containerCounts = Container::query()
            ->selectRaw('COUNT(*) AS total_containers')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_active THEN 1 ELSE 0 END), 0) AS active_containers')
            ->first();

        $totalContainers = (int) $containerCounts?->getAttribute('total_containers');
        $activeContainers = (int) $containerCounts?->getAttribute('active_containers');
        $inactiveContainers = $totalContainers - $activeContainers;

        $inUseContainers = DispatchEntry::query()
            ->whereNull('return_date')
            ->whereHas('dispatch', fn ($query) => $query->whereState('state', DispatchApprovedState::class))
            ->whereHas('container')
            ->distinct('container_id')
            ->count('container_id');

        $utilization = $totalContainers === 0
            ? 0.0
            : round(($inUseContainers / $totalContainers) * 100, 2);

        $returnedEntriesQuery = DispatchEntry::query()
            ->whereNotNull('return_date')
            ->whereHas('dispatch')
            ->whereHas('container');

        $returnCounts = $returnedEntriesQuery
            ->selectRaw('COUNT(*) AS returned_entries')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN return_condition = ? THEN 1 ELSE 0 END), 0) AS damaged_entries',
                [ReturnCondition::Damaged->value],
            )
            ->first();

        $returnedEntries = (int) $returnCounts?->getAttribute('returned_entries');
        $damagedEntries = (int) $returnCounts?->getAttribute('damaged_entries');
        $damageRate = $returnedEntries === 0
            ? 0.0
            : round(($damagedEntries / $returnedEntries) * 100, 2);

        $openEntries = DispatchEntry::query()
            ->with('dispatch:id,dispatched_at')
            ->whereNull('return_date')
            ->whereHas('dispatch', fn ($query) => $query
                ->whereState('state', DispatchApprovedState::class)
                ->whereNotNull('dispatched_at'))
            ->whereHas('container')
            ->get(['id', 'dispatch_id']);

        $ages = $openEntries
            ->map(fn (DispatchEntry $entry): int => max(
                0,
                (int) ($entry->dispatch->dispatched_at?->diffInDays(now()) ?? 0),
            ))
            ->values();

        $averageAge = $ages->isEmpty() ? 0.0 : round((float) $ages->average(), 1);
        $oldestAge = $ages->isEmpty() ? 0 : (int) $ages->max();

        $lostEntries = DispatchEntry::query()
            ->with('container:id,replacement_cost,replacement_currency')
            ->where('return_condition', ReturnCondition::Lost)
            ->whereHas('dispatch')
            ->whereHas('container')
            ->get(['id', 'container_id']);

        /** @var Collection<string, float> $exposureByCurrency */
        $exposureByCurrency = $lostEntries
            ->groupBy(fn (DispatchEntry $entry): string => mb_strtoupper($entry->container->replacement_currency))
            ->map(fn (Collection $entries): float => (float) $entries->sum(
                fn (DispatchEntry $entry): float => (float) $entry->container->replacement_cost,
            ))
            ->sortKeys();

        return [
            Stat::make('Total Containers', number_format($totalContainers))
                ->description("{$activeContainers} active · {$inactiveContainers} inactive")
                ->icon(Heroicon::OutlinedCube)
                ->color('primary'),

            Stat::make('Containers In Use', number_format($inUseContainers))
                ->description('Approved and not yet returned')
                ->icon(Heroicon::OutlinedTruck)
                ->color($inUseContainers > 0 ? 'primary' : 'gray'),

            Stat::make('Fleet Utilization', $this->formatPercentage($utilization))
                ->description($this->utilizationDescription($totalContainers, $utilization))
                ->descriptionIcon($this->utilizationIcon($totalContainers, $utilization))
                ->icon(Heroicon::OutlinedChartPie)
                ->color($this->utilizationColor($totalContainers, $utilization)),

            Stat::make('Return Damage Rate', $this->formatPercentage($damageRate))
                ->description("{$damagedEntries} damaged of {$returnedEntries} returned")
                ->descriptionIcon($damageRate > 10 ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedShieldCheck)
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->color($this->damageColor($returnedEntries, $damageRate)),

            Stat::make('Average Dispatch Age', $this->formatDays($averageAge))
                ->description($openEntries->isEmpty()
                    ? 'No open approved dispatches'
                    : "{$openEntries->count()} open · oldest {$this->formatDays($oldestAge)}")
                ->descriptionIcon($oldestAge >= 14 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedClock)
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color($this->agingColor($openEntries->count(), $averageAge, $oldestAge)),

            Stat::make('Loss Exposure', $this->formatExposureValue($exposureByCurrency))
                ->description($this->formatExposureDescription($lostEntries->count(), $exposureByCurrency))
                ->descriptionIcon($lostEntries->isEmpty() ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedExclamationTriangle)
                ->icon(Heroicon::OutlinedBanknotes)
                ->color($lostEntries->isEmpty() ? 'success' : 'danger'),
        ];
    }

    private function formatPercentage(float $value): string
    {
        return number_format($value, 2, '.', '').'%';
    }

    private function formatDays(float|int $days): string
    {
        $formatted = mb_rtrim(mb_rtrim(number_format((float) $days, 1, '.', ''), '0'), '.');

        return $formatted.' '.(((float) $days === 1.0) ? 'day' : 'days');
    }

    private function utilizationDescription(int $totalContainers, float $utilization): string
    {
        return match (true) {
            $totalContainers === 0 => 'No fleet capacity data',
            $utilization >= 90 => 'Near fleet capacity',
            $utilization >= 75 => 'Capacity is tightening',
            default => 'Capacity is available',
        };
    }

    private function utilizationIcon(int $totalContainers, float $utilization): Heroicon
    {
        return match (true) {
            $totalContainers === 0 => Heroicon::OutlinedMinusCircle,
            $utilization >= 75 => Heroicon::OutlinedArrowTrendingUp,
            default => Heroicon::OutlinedCheckCircle,
        };
    }

    private function utilizationColor(int $totalContainers, float $utilization): string
    {
        return match (true) {
            $totalContainers === 0 => 'gray',
            $utilization >= 90 => 'danger',
            $utilization >= 75 => 'warning',
            default => 'success',
        };
    }

    private function damageColor(int $returnedEntries, float $damageRate): string
    {
        return match (true) {
            $returnedEntries === 0 => 'gray',
            $damageRate > 10 => 'danger',
            $damageRate > 5 => 'warning',
            default => 'success',
        };
    }

    private function agingColor(int $openEntries, float $averageAge, int $oldestAge): string
    {
        return match (true) {
            $openEntries === 0 => 'gray',
            $oldestAge >= 30 => 'danger',
            $averageAge >= 14 || $oldestAge >= 14 => 'warning',
            default => 'success',
        };
    }

    /**
     * @param  Collection<string, float>  $exposureByCurrency
     */
    private function formatExposureValue(Collection $exposureByCurrency): string
    {
        if ($exposureByCurrency->isEmpty()) {
            return '0.00';
        }

        if ($exposureByCurrency->count() > 1) {
            return $exposureByCurrency->count().' currencies';
        }

        $currency = (string) $exposureByCurrency->keys()->first();
        $amount = $exposureByCurrency->first();

        return $currency.' '.number_format($amount, 2, '.', ',');
    }

    /**
     * @param  Collection<string, float>  $exposureByCurrency
     */
    private function formatExposureDescription(int $lostEntries, Collection $exposureByCurrency): string
    {
        if ($lostEntries === 0) {
            return 'No recorded losses';
        }

        $containerLabel = $lostEntries === 1 ? 'container' : 'containers';

        if ($exposureByCurrency->count() <= 1) {
            return "{$lostEntries} lost {$containerLabel}";
        }

        $breakdown = $exposureByCurrency
            ->take(2)
            ->map(fn (float $amount, string $currency): string => $currency.' '.number_format($amount, 2, '.', ','))
            ->implode(' · ');

        $remainingCurrencies = $exposureByCurrency->count() - 2;

        if ($remainingCurrencies > 0) {
            $breakdown .= " · +{$remainingCurrencies} more";
        }

        return "{$lostEntries} lost · {$breakdown}";
    }
}
