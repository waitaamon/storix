<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Exports\DispatchExporter;
use Storix\Filament\Imports\DispatchImporter;
use Storix\Filament\Imports\DispatchReturnImporter;
use Storix\Models\Dispatch;

final class DispatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('container.serial')
                ->label('Container')
                ->searchable()
                ->sortable(),

            TextColumn::make('customer.name')
                ->label('Customer')
                ->searchable(),

            TextColumn::make('dispatched_at')
                ->dateTime()
                ->sortable(),

            TextColumn::make('return_date')
                ->dateTime()
                ->sortable(),

            TextColumn::make('status')->badge(),
        ])
            ->filters([
                SelectFilter::make('return_condition')
                    ->options(ReturnCondition::class),

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
                ImportAction::make()
                    ->label('Bulk Dispatches')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->outlined()
                    ->importer(DispatchImporter::class),
                ImportAction::make('importReturns')
                    ->label('Bulk Returns')
                    ->color('success')
                    ->outlined()
                    ->icon('heroicon-o-arrow-down-on-square-stack')
                    ->importer(DispatchReturnImporter::class),
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchExporter::class),
                ]),
            ]);
    }
}
