<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\ImportAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Filament\Imports\DispatchEntryImporter;

final class ContainersRelationManager extends RelationManager
{
    protected static string $relationship = 'containers';

    #[Override]
    public function isReadOnly(): bool
    {
        return auth()->user()->cannot('update', $this->ownerRecord);
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
                ImportAction::make('Bulk Import')
                    ->icon('heroicon-o-document-arrow-up')
                    ->outlined()
                    ->color('primary')
                    ->size(Size::ExtraSmall)
                    ->label('Import '.str(Config::string('storix.labels.container'))->plural()->headline())
                    ->importer(DispatchEntryImporter::class)
                    ->options(['dispatch_id' => $this->ownerRecord->id]),

                AttachAction::make()
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->availableForDispatch())
                    ->icon('heroicon-o-plus')
                    ->label(fn () => 'Add '.Config::string('storix.labels.container')),
            ])
            ->recordActions([
                DetachAction::make()
                    ->iconButton(),
            ]);
    }
}
