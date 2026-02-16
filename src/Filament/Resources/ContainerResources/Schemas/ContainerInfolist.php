<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ContainerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('serial'),
                    IconEntry::make('is_active')->boolean(),
                    TextEntry::make('description'),
                ]),
        ]);
    }
}
