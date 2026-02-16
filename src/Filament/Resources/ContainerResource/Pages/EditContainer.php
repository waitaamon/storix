<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Resources\ContainerResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use WaitAmon\Storix\Filament\Resources\ContainerResource;

final class EditContainer extends EditRecord
{
    protected static string $resource = ContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }
}
