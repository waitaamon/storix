<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntries;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Storix\Models\DispatchEntry;

final class DispatchEntryResource extends Resource
{
    protected static ?string $model = DispatchEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('container.name')->searchable()->label('Name'),

                TextColumn::make('container.serial')->searchable()->label('Serial'),

                TextColumn::make('dispatch.customer.name')->searchable()->label('Customer'),

                TextColumn::make('dispatch.delivery_note')->searchable()->label('Delivery Note'),

                TextColumn::make('dispatch.dispatched_at')->date()->label('Dispatched At'),

                TextColumn::make('dispatch.dispatchedBy.name')->label('Dispatched By'),

                TextColumn::make('return_date')->date(),

                TextColumn::make('return_condition')->badge()->label('Return Condition'),

                TextColumn::make('received.name')->badge()->label('Received By'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDispatchEntries::route('/'),
        ];
    }
}
