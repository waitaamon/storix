<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns()
                ->columnSpanFull()
                ->schema([
                    Select::make('container_id')
                        ->relationship('container', 'serial')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('customer_id')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('delivery_note')
                        ->maxLength(200)
                        ->required(),

                    DateTimePicker::make('dispatched_at')
                        ->default(now())
                        ->closeOnDateSelection()
                        ->native(false)
                        ->required(),

                    Textarea::make('dispatched_note')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
