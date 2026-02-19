<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Exports\DispatchExporter;

final class DispatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'dispatches';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('customer_id')->required()->numeric(),
            TextInput::make('dispatched_by')->required()->numeric(),
            DateTimePicker::make('dispatched_at')->required(),
            TextInput::make('delivery_note_id'),
            Textarea::make('dispatched_note'),
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
                TextColumn::make('customer.name')->label('Customer')->searchable(),
                TextColumn::make('dispatched_at')->dateTime()->sortable(),
                TextColumn::make('pivot.return_date')->dateTime()->label('Return Date'),
                TextColumn::make('pivot.return_condition')
                    ->label('Return Condition')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchExporter::class),
                ]),
            ]);
    }
}
