<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntries\Pages;

use Filament\Resources\Pages\CreateRecord;
use Storix\Filament\Resources\DispatchEntries\DispatchEntryResource;

final class CreateDispatchEntry extends CreateRecord
{
    protected static string $resource = DispatchEntryResource::class;
}
