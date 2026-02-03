<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Resources\Containers\Pages;

use Filament\Resources\Pages\CreateRecord;
use Storix\ContainerMovement\Filament\Resources\Containers\ContainerResource;

final class CreateContainer extends CreateRecord
{
    protected static string $resource = ContainerResource::class;
}
