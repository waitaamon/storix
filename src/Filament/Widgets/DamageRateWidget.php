<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Enums\ReturnCondition;
use Storix\Models\Dispatch;

final class DamageRateWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $returned = Dispatch::query()->whereNotNull('return_date')->count();
        $damaged = Dispatch::query()->where('return_condition', ReturnCondition::Damaged)->count();
        $rate = $returned > 0 ? round(($damaged / $returned) * 100, 2) : 0.0;

        return [
            Stat::make('Returned Dispatches', (string) $returned),
            Stat::make('Damaged Returns', (string) $damaged),
            Stat::make('Damage Rate', sprintf('%.2f%%', $rate)),
        ];
    }
}
