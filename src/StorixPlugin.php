<?php

declare(strict_types=1);

namespace Storix;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Storix\Filament\Resources\ContainerDispatches\ContainerDispatchResource;
use Storix\Filament\Resources\ContainerReturns\ContainerReturnResource;
use Storix\Filament\Resources\Containers\ContainerResource;
use Storix\Filament\Widgets\ContainersWithCustomersWidget;
use Storix\Filament\Widgets\DispatchReturnTrendChartWidget;
use Storix\Filament\Widgets\OverdueReturnsWidget;

final class StorixPlugin implements Plugin
{
    /** Create a new plugin instance. */
    public static function make(): self
    {
        return new self();
    }

    /** Get the unique plugin identifier. */
    public function getId(): string
    {
        return 'storix';
    }

    /** Register the plugin's resources and widgets with the panel. */
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

    /** Boot the plugin (no-op). */
    public function boot(Panel $panel): void
    {
        // No-op.
    }
}
