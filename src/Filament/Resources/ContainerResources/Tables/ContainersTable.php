<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;
use Storix\Filament\Exports\ContainerExporter;
use Storix\Filament\Imports\ContainerImporter;
use Storix\Models\Container;

final class ContainersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),

            TextColumn::make('serial')->searchable()->sortable(),

            IconColumn::make('is_active')->boolean(),

            TextColumn::make('replacement_cost')
                ->money(fn (Container $record): string => $record->replacement_currency)
                ->sortable(),

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
                    ->authorize(fn (Container $record) => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()
                    ->iconButton()
                    ->slideOver()
                    ->authorize(fn (Container $record) => auth()->user()?->can('update', $record) ?? false),
            ])
            ->toolbarActions([
                ImportAction::make('Bulk '.str(Config::string('storix.labels.container'))->plural()->headline().' Import')
                    ->icon('heroicon-o-document-arrow-up')
                    ->outlined()
                    ->color('primary')
                    ->size(Size::ExtraSmall)
                    ->label('Import '.str(Config::string('storix.labels.container'))->plural()->headline())
                    ->importer(ContainerImporter::class),
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContainerExporter::class),
                ]),
            ]);
    }
}
