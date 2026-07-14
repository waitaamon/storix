<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Pages;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Override;
use Storix\Filament\Resources\ContainerResources\ContainerResource;
use Storix\Models\Container;

final class ViewContainer extends ViewRecord
{
    protected static string $resource = ContainerResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->authorize(fn (Container $record) => auth()->user()?->can('update', $record) ?? false),

                DeleteAction::make()
                    ->authorize(fn (Container $record) => auth()->user()?->can('delete', $record) ?? false),
            ]),
        ];
    }
}
