<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Exports\DispatchExporter;

final class DispatchEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('return_condition')
                ->options(ReturnCondition::class)
                ->native(false),
            DateTimePicker::make('return_date'),
            Textarea::make('return_note'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('container.serial')
                    ->label('Serial')
                    ->searchable(),

                TextColumn::make('container.name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('receivedBy.name')
                    ->label('Received By'),

                TextColumn::make('return_date')
                    ->date(),

                TextColumn::make('return_condition')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn () => auth()->user()->can('update', $this->ownerRecord)),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->authorize(fn () => auth()->user()->can('update', $this->ownerRecord)),
                DeleteAction::make()
                    ->iconButton()
                    ->authorize(fn () => auth()->user()->can('update', $this->ownerRecord)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchExporter::class),
                ]),
            ]);
    }
}
