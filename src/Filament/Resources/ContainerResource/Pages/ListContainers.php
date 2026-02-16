<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Resources\ContainerResource\Pages;

use Filament\Resources\Pages\ListRecords;
use WaitAmon\Storix\Filament\Resources\ContainerResource;

final class ListContainers extends ListRecords
{
    protected static string $resource = ContainerResource::class;
}
