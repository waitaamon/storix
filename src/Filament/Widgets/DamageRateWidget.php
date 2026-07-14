<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Override;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Enums\ReturnCondition;
use Storix\Models\DispatchEntry;

final class DamageRateWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    #[Override]
    protected function getStats(): array
    {
        $returnedEntriesQuery = DispatchEntry::query()
            ->whereNotNull('return_date')
            ->whereHas('dispatch')
            ->whereHas('container');

        $returned = (clone $returnedEntriesQuery)->count();

        $damaged = (clone $returnedEntriesQuery)
            ->where('return_condition', ReturnCondition::Damaged)
            ->count();

        $rate = $returned > 0 ? round(($damaged / $returned) * 100, 2) : 0.0;

        return [
            Stat::make('Returned Dispatches', (string) $returned),
            Stat::make('Damaged Returns', (string) $damaged),
            Stat::make('Damage Rate', sprintf('%.2f%%', $rate)),
        ];
    }
}
