<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;
use Storix\Filament\Resources\ContainerResources\ContainerResource;
use Storix\Filament\Widgets\ContainerUtilizationWidget;

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
                ->authorize(fn () => auth()->user()->can('create', ContainerResource::getModel())),
        ];
    }

    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            ContainerUtilizationWidget::class,
        ];
    }
}
