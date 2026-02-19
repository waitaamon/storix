<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Storix\Filament\Exports\ContainerExporter;
use Storix\Models\Container;

final class ContainersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),

            TextColumn::make('serial')->searchable()->sortable(),

            IconColumn::make('is_active')->boolean(),

            TextColumn::make('dispatches_count')
                ->counts('dispatches')
                ->label('Dispatches'),

            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->authorize(fn (Container $record) => auth()->user()->can('view', $record)),
                EditAction::make()
                    ->iconButton()
                    ->slideOver()
                    ->authorize(fn (Container $record) => auth()->user()->can('update', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContainerExporter::class),
                ]),
            ]);
    }
}
