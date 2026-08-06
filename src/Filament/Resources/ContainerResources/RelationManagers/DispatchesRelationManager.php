<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'customer',
                'deliveryNote',
            ]))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('deliveryNote.code')
                    ->label('Delivery Note')
                    ->searchable(),

                TextColumn::make('dispatched_at')
                    ->date()
                    ->sortable(),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchExporter::class),
                ]),
            ]);
    }
}
