<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Storix\Support\CustomerQuery;
use Storix\Support\FinancialYear;

final class ContainerReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        $year = FinancialYear::selected();

        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('customer_id')
                        ->relationship(name: 'customer', titleAttribute: 'name', modifyQueryUsing: fn (Builder $query): Builder => CustomerQuery::modify($query))
                        ->searchable()
                        ->preload()
                        ->required(),

                    DatePicker::make('transaction_date')
                        ->default(today())
                        ->native(false)
                        ->minDate($year?->start_date)
                        ->maxDate($year?->end_date)
                        ->required(),

                    Textarea::make('note')
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
