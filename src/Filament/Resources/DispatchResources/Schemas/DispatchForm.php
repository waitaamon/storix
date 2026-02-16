<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                        ->relationship(
                            name: 'container',
                            titleAttribute: 'serial',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->availableForDispatch()
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('customer_id')
                        ->relationship(
                            name: 'customer',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->whereRelation('category', 'slug', 'accounts-receivable')
                        )
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
