<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Resources\ContainerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use WaitAmon\Storix\Enums\DispatchStatus;

final class DispatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'dispatches';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('customer_id')->required()->uuid(),
            Forms\Components\TextInput::make('dispatched_by')->required()->uuid(),
            Forms\Components\DateTimePicker::make('dispatched_at')->required(),
            Forms\Components\Textarea::make('delivery_note'),
            Forms\Components\Textarea::make('dispatched_note'),
            Forms\Components\Select::make('return_condition')
                ->options([
                    'good' => 'Returned Good',
                    'damaged' => 'Returned Damaged',
                    'lost' => 'Lost',
                ])
                ->native(false),
            Forms\Components\DateTimePicker::make('return_date'),
            Forms\Components\Textarea::make('return_note'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('customer_id')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('dispatched_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('return_date')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DispatchStatus $state): string => $state->label())
                    ->color(fn (DispatchStatus $state): string => $state->color()),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
