<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class ContainerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                TextEntry::make('name'),
                TextEntry::make('serial'),
                IconEntry::make('is_active')->boolean(),
                TextEntry::make('description'),
            ]);
    }
}
