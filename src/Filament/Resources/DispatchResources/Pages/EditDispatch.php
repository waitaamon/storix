<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Pages;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Override;
use Storix\Filament\Resources\DispatchResources\DispatchResource;
use Storix\Models\Dispatch;

final class EditDispatch extends EditRecord
{
    protected static string $resource = DispatchResource::class;

    #[Override]
    public function getRelationManagers(): array
    {
        return [];
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ViewAction::make()
                    ->authorize(fn (Dispatch $record) => auth()->user()?->can('view', $record) ?? false),
                DeleteAction::make()
                    ->authorize(fn (Dispatch $record) => auth()->user()?->can('delete', $record) ?? false),
            ]),
        ];
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
