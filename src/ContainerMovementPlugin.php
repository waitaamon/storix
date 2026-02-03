<?php

declare(strict_types=1);

namespace Storix\ContainerMovement;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Storix\ContainerMovement\Filament\Resources\ContainerDispatches\ContainerDispatchResource;
use Storix\ContainerMovement\Filament\Resources\ContainerReturns\ContainerReturnResource;
use Storix\ContainerMovement\Filament\Resources\Containers\ContainerResource;
use Storix\ContainerMovement\Filament\Widgets\ContainersWithCustomersWidget;
use Storix\ContainerMovement\Filament\Widgets\DispatchReturnTrendChartWidget;
use Storix\ContainerMovement\Filament\Widgets\OverdueReturnsWidget;

final class ContainerMovementPlugin implements Plugin
{
    public function getId(): string
    {
        return 'container-movement';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ContainerResource::class,
            ContainerDispatchResource::class,
            ContainerReturnResource::class,
        ])->widgets([
            ContainersWithCustomersWidget::class,
            OverdueReturnsWidget::class,
            DispatchReturnTrendChartWidget::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op.
    }

    public static function make(): self
    {
        return new self();
    }
}
