<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class ContainerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('serial')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Toggle::make('is_active')
                ->default(true)
                ->required(),

            TextInput::make('replacement_cost')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            TextInput::make('replacement_currency')
                ->maxLength(3)
                ->default('USD')
                ->required(),

            Textarea::make('description')
                ->columnSpanFull(),
        ]);
    }
}
