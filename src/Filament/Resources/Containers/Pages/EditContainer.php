<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Resources\Containers\Pages;

use Filament\Resources\Pages\EditRecord;
use Storix\ContainerMovement\Filament\Resources\Containers\ContainerResource;

final class EditContainer extends EditRecord
{
    protected static string $resource = ContainerResource::class;
}
