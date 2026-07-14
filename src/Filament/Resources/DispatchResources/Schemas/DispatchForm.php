<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;
use Storix\Models\Container;
use Storix\Support\FinancialYear;

final class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        $year = FinancialYear::selected();

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
                        ->minDate($year?->start_date)
                        ->maxDate($year?->end_date)
                        ->required(),

                    Select::make('container_ids')
                        ->label(str(Config::string('storix.labels.container'))->plural()->headline()->toString())
                        ->options(static fn (): array => Container::query()
                            ->availableForDispatch()
                            ->orderBy('serial')
                            ->pluck('serial', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visibleOn('create')
                        ->columnSpanFull(),

                    Textarea::make('dispatch_note')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
