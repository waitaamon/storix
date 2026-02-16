<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Pages;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Override;
use Storix\Filament\Resources\DispatchResources\DispatchResource;
use Storix\Models\Dispatch;

final class ViewDispatch extends ViewRecord
{
    protected static string $resource = DispatchResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make()
                    ->authorize(fn (Dispatch $record) => auth()->user()->can('update', $record)),

                DeleteAction::make()
                    ->authorize(fn (Dispatch $record) => auth()->user()->can('delete', $record)),
            ]),
        ];
    }
}
