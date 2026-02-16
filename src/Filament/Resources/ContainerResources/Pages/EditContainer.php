<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Pages;

use Filament\Resources\Pages\EditRecord;
use Storix\Filament\Resources\ContainerResources\ContainerResource;

final class EditContainer extends EditRecord
{
    protected static string $resource = ContainerResource::class;
}
