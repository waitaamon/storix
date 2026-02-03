<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Resources\Containers\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Storix\ContainerMovement\Filament\Resources\Containers\ContainerResource;

final class ListContainers extends ListRecords
{
    protected static string $resource = ContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
