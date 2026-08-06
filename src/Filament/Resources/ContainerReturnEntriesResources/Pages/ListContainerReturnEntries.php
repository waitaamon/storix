<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnEntriesResources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;
use Storix\Filament\Resources\ContainerReturnEntriesResources\ContainerReturnEntryResource;

final class ListContainerReturnEntries extends ListRecords
{
    #[Override]
    protected static string $resource = ContainerReturnEntryResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->slideOver()
                ->authorize(
                    fn (): bool => auth()->user()?->can('create', ContainerReturnEntryResource::getModel()) ?? false,
                ),
        ];
    }
}
