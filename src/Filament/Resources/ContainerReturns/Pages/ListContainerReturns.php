<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Resources\ContainerReturns\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Storix\ContainerMovement\Filament\Resources\ContainerReturns\ContainerReturnResource;

final class ListContainerReturns extends ListRecords
{
    protected static string $resource = ContainerReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
