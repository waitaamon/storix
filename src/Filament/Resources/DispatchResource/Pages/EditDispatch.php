<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Resources\DispatchResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use WaitAmon\Storix\Filament\Resources\DispatchResource;

final class EditDispatch extends EditRecord
{
    protected static string $resource = DispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }
}
