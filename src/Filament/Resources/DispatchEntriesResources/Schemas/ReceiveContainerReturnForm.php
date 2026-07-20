<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntriesResources\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Storix\Enums\ReturnCondition;
use Storix\Support\FinancialYear;

final class ReceiveContainerReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }

    /** @return list<Component> */
    public static function components(): array
    {
        $year = FinancialYear::selected();

        return [
            DatePicker::make('return_date')
                ->native(false)
                ->default(today())
                ->minDate($year?->start_date)
                ->maxDate($year?->end_date)
                ->closeOnDateSelection()
                ->required(),

            Select::make('return_condition')
                ->options(ReturnCondition::class)
                ->default(ReturnCondition::Good)
                ->native(false)
                ->required(),

            Textarea::make('return_note')
                ->nullable()
                ->columnSpanFull(),
        ];
    }
}
