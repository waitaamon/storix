<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Override;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Models\DispatchEntry;
use Storix\Models\States\DispatchApprovedState;

final class ContainerAgingReportWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $entries = DispatchEntry::query()
            ->with('dispatch')
            ->whereNull('return_date')
            ->whereHas('dispatch', fn ($query) => $query
                ->whereState('state', DispatchApprovedState::class)
                ->whereNotNull('dispatched_at'))
            ->get();

        $ages = $entries
            ->map(fn (DispatchEntry $entry): int => (int) ($entry->dispatch->dispatched_at?->diffInDays(now()) ?? 0))
            ->values();

        $openCount = $ages->count();
        $average = $openCount === 0 ? 0 : round((float) $ages->avg(), 1);
        $oldest = $openCount === 0 ? 0 : $ages->max();

        return [
            Stat::make('Open Dispatches', (string) $openCount),
            Stat::make('Average Aging (days)', (string) $average),
            Stat::make('Oldest Open (days)', (string) $oldest),
        ];
    }
}
