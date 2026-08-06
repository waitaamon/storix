<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;

final class ListContainerReturns extends ListRecords
{
    #[Override]
    protected static string $resource = ContainerReturnResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->authorize(
                    fn (): bool => auth()->user()?->can('create', ContainerReturnResource::getModel()) ?? false,
                ),
        ];
    }
}
