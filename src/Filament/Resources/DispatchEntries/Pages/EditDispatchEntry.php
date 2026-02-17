<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntries\Pages;

use Filament\Resources\Pages\EditRecord;
use Storix\Filament\Resources\DispatchEntries\DispatchEntryResource;

final class EditDispatchEntry extends EditRecord
{
    protected static string $resource = DispatchEntryResource::class;
}
