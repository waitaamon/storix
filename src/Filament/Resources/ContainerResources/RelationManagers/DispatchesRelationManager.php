<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Override;
use Storix\Filament\Exports\DispatchExporter;

final class DispatchesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'dispatches';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('deliveryNote.customer.name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('deliveryNote.code')
                    ->label('Delivery Note')
                    ->searchable(),

                TextColumn::make('dispatched_at')
                    ->date()
                    ->sortable(),

                TextColumn::make('pivot.return_date')
                    ->dateTime()
                    ->label('Return Date'),

                TextColumn::make('pivot.return_condition')
                    ->label('Return Condition')
                    ->badge(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchExporter::class),
                ]),
            ]);
    }
}
