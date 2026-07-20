<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Actions\AttachContainersToDispatchAction;
use Storix\Filament\Exports\DispatchEntryExporter;
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

                CreateAction::make('addContainers')
                    ->schema([
                        Select::make('container_ids')
                            ->label(fn (): string => str(Config::string('storix.labels.container'))->plural()->headline()->toString())
                            ->options(static fn (): array => Container::query()
                                ->availableForDispatch()
                                ->orderBy('serial')
                                ->pluck('serial', 'id')
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->using(function (array $data): DispatchEntry {
                        $containerIds = $this->containerIds($data);

                        app(AttachContainersToDispatchAction::class)->handle($this->ownerRecord, $containerIds);

                        return DispatchEntry::query()
                            ->where('dispatch_id', $this->ownerRecord->id)
                            ->whereIn('container_id', $containerIds)
                            ->firstOrFail();
                    })
                    ->createAnother(false)
                    ->icon('heroicon-o-plus')
                    ->label('Add')
                    ->modalHeading(fn (): string => 'Add '.str(Config::string('storix.labels.container'))->plural()->headline()->toString())
                    ->modalSubmitActionLabel('Add'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchEntryExporter::class),
                ]),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->iconButton(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int|string>
     */
    private function containerIds(array $data): array
    {
        $selectedIds = $data['container_ids'] ?? [];

        if (! is_array($selectedIds)) {
            return [];
        }

        $containerIds = [];

        foreach ($selectedIds as $selectedId) {
            if (is_int($selectedId) || is_string($selectedId)) {
                $containerIds[] = $selectedId;
            }
        }

        return $containerIds;
    }
}
