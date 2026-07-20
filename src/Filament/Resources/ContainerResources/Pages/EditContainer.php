<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Pages;

use Filament\Resources\Pages\EditRecord;
use Override;
use Storix\Filament\Resources\ContainerResources\ContainerResource;

final class EditContainer extends EditRecord
{
    #[Override]
    protected static string $resource = ContainerResource::class;
}
