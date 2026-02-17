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
use Storix\Models\Container;

final class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('customer_id')
                        ->relationship(
                            name: 'customer',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereRelation('category', 'slug', 'accounts-receivable')->where('is_active', true)
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

                    Select::make('containers')
                        ->relationship(
                            name: 'containers',
                            titleAttribute: 'serial',
                            modifyQueryUsing: static fn (Builder $query): Builder => $query->availableForDispatch()
                        )
                        ->getOptionLabelFromRecordUsing(static fn (Container $record): string => $record->serial)
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),

                    Textarea::make('dispatched_note')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
