<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntries;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Storix\Filament\Exports\DispatchEntryExporter;
use Storix\Models\DispatchEntry;
use UnitEnum;

final class DispatchEntryResource extends Resource
{
    protected static ?string $model = DispatchEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Storix';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('container.name')
                    ->searchable()
                    ->label('Name'),

                TextColumn::make('container.serial')
                    ->searchable()
                    ->label('Serial'),

                TextColumn::make('dispatch.deliveryNote.customer.name')
                    ->searchable()
                    ->label('Customer'),

                TextColumn::make('dispatch.deliveryNote.code')
                    ->searchable()
                    ->label('Delivery Note'),

                TextColumn::make('dispatch.dispatched_at')
                    ->date()
                    ->label('Dispatched At'),

                TextColumn::make('dispatch.dispatchedBy.name')
                    ->label('Dispatched By'),

                TextColumn::make('return_date')
                    ->date(),

                TextColumn::make('return_condition')
                    ->badge()
                    ->label('Return Condition'),

                TextColumn::make('receivedBy.name')
                    ->label('Received By'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchEntryExporter::class),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDispatchEntries::route('/'),
        ];
    }
}
