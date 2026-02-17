<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Storix\Filament\Exports\DispatchExporter;
use Storix\Models\Dispatch;

final class DispatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([

            TextColumn::make('customer.name')
                ->label('Customer')
                ->searchable(),

            TextColumn::make('delivery_note')
                ->searchable(),

            TextColumn::make('dispatched_at')
                ->date()
                ->sortable(),

            TextColumn::make('dispatchedBy.name')
                ->label('Dispatched By'),
        ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->authorize(fn (Dispatch $record) => auth()->user()->can('view', $record)),
                EditAction::make()
                    ->iconButton()
                    ->authorize(fn (Dispatch $record) => auth()->user()->can('update', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchExporter::class),
                ]),
            ]);
    }
}
