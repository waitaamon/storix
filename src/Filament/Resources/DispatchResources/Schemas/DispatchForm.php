<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Storix\Models\Container;

final class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        $financialYearServiceClass = Config::string('storix.financial_year_service_class', 'App\\Services\\FinancialYearService');

        $year = (new $financialYearServiceClass)::selectedFinancialYear();

        return $schema->components([
            Section::make()
                ->columns()
                ->columnSpanFull()
                ->schema([

                    Select::make('delivery_note_id')
                        ->relationship(
                            name: 'deliveryNote',
                            titleAttribute: 'code',
                            modifyQueryUsing: Config::get('storix.delivery_note_query_modifier') ?? fn ($query) => $query
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    DateTimePicker::make('dispatched_at')
                        ->default(now())
                        ->closeOnDateSelection()
                        ->native(false)
                        ->minDate($year?->start_date ?? today())
                        ->maxDate($year?->end_date ?? today())
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
                        ->nullable()
                        ->columnSpanFull(),

                    Textarea::make('dispatch_note')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
