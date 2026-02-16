<?php

declare(strict_types=1);

namespace WaitAmon\Storix;

use Filament\Contracts\Plugin;
use Filament\Panel;
use WaitAmon\Storix\Filament\Resources\ContainerResource;
use WaitAmon\Storix\Filament\Resources\DispatchResource;
use WaitAmon\Storix\Filament\Widgets\ContainerAgingReportWidget;
use WaitAmon\Storix\Filament\Widgets\ContainerUtilizationWidget;
use WaitAmon\Storix\Filament\Widgets\DamageRateWidget;
use WaitAmon\Storix\Filament\Widgets\LostExposureWidget;

final class StorixPlugin implements Plugin
{
    public function getId(): string
    {
        return 'storix';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                ContainerResource::class,
                DispatchResource::class,
            ])
            ->widgets([
                ContainerUtilizationWidget::class,
                DamageRateWidget::class,
                ContainerAgingReportWidget::class,
                LostExposureWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // Plugin does not require per-panel runtime boot logic yet.
    }

    public static function make(): self
    {
        return new self();
    }
}
