<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Actions\AttachContainersToDispatchAction;
use Storix\Filament\Imports\DispatchEntryImporter;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;

/**
 * @property Dispatch $ownerRecord
 */
final class ContainersRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'entries';

    #[Override]
    public function isReadOnly(): bool
    {
        return auth()->user()?->cannot('update', $this->ownerRecord) ?? true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('container.serial')
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
                ImportAction::make('Bulk Import')
                    ->icon('heroicon-o-document-arrow-up')
                    ->outlined()
                    ->color('primary')
                    ->size(Size::ExtraSmall)
                    ->label('Import '.str(Config::string('storix.labels.container'))->plural()->headline())
                    ->importer(DispatchEntryImporter::class)
                    ->options(['dispatch_id' => $this->ownerRecord->id]),

                CreateAction::make('addContainer')
                    ->schema([
                        Select::make('container_id')
                            ->label(Config::string('storix.labels.container'))
                            ->options(static fn (): array => Container::query()
                                ->availableForDispatch()
                                ->orderBy('serial')
                                ->pluck('serial', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->using(function (array $data): DispatchEntry {
                        app(AttachContainersToDispatchAction::class)->handle($this->ownerRecord, [(int) $data['container_id']]);

                        return DispatchEntry::query()
                            ->where('dispatch_id', $this->ownerRecord->id)
                            ->where('container_id', $data['container_id'])
                            ->firstOrFail();
                    })
                    ->icon('heroicon-o-plus')
                    ->label(fn (): string => 'Add '.Config::string('storix.labels.container')),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->iconButton(),
            ]);
    }
}
