<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\Containers\Pages;

use Filament\Resources\Pages\CreateRecord;
use Storix\Filament\Resources\Containers\ContainerResource;

final class CreateContainer extends CreateRecord
{
    protected static string $resource = ContainerResource::class;
}
