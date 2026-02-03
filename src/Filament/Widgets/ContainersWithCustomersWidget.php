<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Storix\ContainerMovement\Models\Container;

final class ContainersWithCustomersWidget extends StatsOverviewWidget
{
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
