<?php

declare(strict_types=1);

namespace Storix;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Storix\Filament\Resources\ContainerResources\ContainerResource;
use Storix\Filament\Resources\DispatchEntriesResources\DispatchEntryResource;
use Storix\Filament\Resources\DispatchResources\DispatchResource;
use Storix\Filament\Widgets\ContainerAgingReportWidget;
use Storix\Filament\Widgets\ContainerUtilizationWidget;
use Storix\Filament\Widgets\DamageRateWidget;
use Storix\Filament\Widgets\LostExposureWidget;

final class StorixPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

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
                DispatchEntryResource::class,
            ])
            ->widgets([
                ContainerUtilizationWidget::class,
                DamageRateWidget::class,
                ContainerAgingReportWidget::class,
                LostExposureWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Storix')
                    ->icon('heroicon-s-inbox-stack'),
            ]);
    }

    public function boot(Panel $panel): void {}
}
