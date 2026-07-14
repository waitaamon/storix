<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;
use Storix\Filament\Resources\DispatchResources\DispatchResource;

final class ListDispatches extends ListRecords
{
    protected static string $resource = DispatchResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->slideOver()
                ->authorize(fn () => auth()->user()?->can('create', DispatchResource::getModel()) ?? false),
        ];
    }
}
