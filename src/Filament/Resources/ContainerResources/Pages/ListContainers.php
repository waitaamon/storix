<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;
use Storix\Filament\Resources\ContainerResources\ContainerResource;
use Storix\Filament\Widgets\ContainerAgingReportWidget;
use Storix\Filament\Widgets\ContainerUtilizationWidget;
use Storix\Filament\Widgets\DamageRateWidget;
use Storix\Filament\Widgets\LostExposureWidget;

final class ListContainers extends ListRecords
{
    protected static string $resource = ContainerResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->slideOver()
                ->authorize(fn () => auth()->user()?->can('create', ContainerResource::getModel()) ?? false),
        ];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            ContainerUtilizationWidget::class,
            DamageRateWidget::class,
            ContainerAgingReportWidget::class,
            LostExposureWidget::class,
        ];
    }
}
