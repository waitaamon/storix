<?php

declare(strict_types=1);

namespace Storix\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\Models\Container;

final class ContainersWithCustomersWidget extends StatsOverviewWidget
{
    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $count = Container::query()
            ->withCustomers()
            ->count();

        return [
            Stat::make('Containers With Customers', number_format($count))
                ->description('Currently dispatched and not yet returned')
                ->color('warning'),
        ];
    }
}
