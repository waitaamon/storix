<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;

final class ContainersRelationManager extends RelationManager
{
    protected static string $relationship = 'containers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('container_id')
                ->relationship(
                    name: 'container',
                    titleAttribute: 'serial',
                    modifyQueryUsing: static fn (Builder $query): Builder => $query->availableForDispatch()
                )
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial')
            ->columns([
                TextColumn::make('serial')
                    ->label('Serial')
                    ->searchable(),

                TextColumn::make('name')
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
                AttachAction::make()
                    ->multiple()
                    ->preloadRecordSelect()
                    ->icon('heroicon-o-plus')
                    ->label(fn () => 'Add '.Config::string('storix.labels.container'))
                    ->authorize(fn () => auth()->user()->can('update', $this->ownerRecord)),
            ])
            ->recordActions([
                DetachAction::make()
                    ->iconButton()
                    ->authorize(fn () => auth()->user()->can('update', $this->ownerRecord)),
            ]);
    }
}
