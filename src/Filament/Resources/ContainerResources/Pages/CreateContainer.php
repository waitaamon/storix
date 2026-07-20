<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Pages;

use Filament\Resources\Pages\CreateRecord;
use Override;
use Storix\Filament\Resources\ContainerResources\ContainerResource;

final class CreateContainer extends CreateRecord
{
    #[Override]
    protected static string $resource = ContainerResource::class;
}
