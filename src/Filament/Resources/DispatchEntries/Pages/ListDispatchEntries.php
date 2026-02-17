<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntries\Pages;

use Filament\Resources\Pages\ListRecords;
use Storix\Filament\Resources\DispatchEntries\DispatchEntryResource;

final class ListDispatchEntries extends ListRecords
{
    protected static string $resource = DispatchEntryResource::class;
}
