<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Pages;

use Filament\Resources\Pages\CreateRecord;
use Storix\Filament\Resources\DispatchResources\DispatchResource;

final class CreateDispatch extends CreateRecord
{
    protected static string $resource = DispatchResource::class;
}
