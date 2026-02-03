<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerDispatches\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Storix\Filament\Resources\ContainerDispatches\ContainerDispatchResource;

final class ListContainerDispatches extends ListRecords
{
    protected static string $resource = ContainerDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
