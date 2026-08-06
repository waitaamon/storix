<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use Storix\Actions\AddContainerReturnEntryAction;
use Storix\Actions\DeleteContainerReturnEntryAction;
use Storix\Actions\UpdateContainerReturnEntryAction;
use Storix\Data\AddContainerReturnEntryData;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Exports\ContainerReturnEntryExporter;
use Storix\Filament\Imports\ContainerReturnEntryImporter;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;

/**
 * @property ContainerReturn $ownerRecord
 */
final class EntriesRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'entries';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return auth()->user()?->cannot('update', $this->ownerRecord) ?? true;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'container',
                'containerReturn',
                'dispatchEntry.dispatch.customer',
            ]))
            ->recordTitleAttribute('container.serial')
            ->columns([
                TextColumn::make('container.serial')
                    ->label('Container')
                    ->searchable(),
                TextColumn::make('container.name')
                    ->label('Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('return_condition'),
                TextColumn::make('dispatchEntry.dispatch.code')
                    ->label('Source Dispatch')
                    ->placeholder('—'),
                IconColumn::make('cross_return')
                    ->boolean(),
                TextColumn::make('note')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->headerActions([
                ImportAction::make('import')
                    ->label('Import Entries')
                    ->icon('heroicon-o-document-arrow-up')
                    ->outlined()
                    ->color('primary')
                    ->size(Size::ExtraSmall)
                    ->importer(ContainerReturnEntryImporter::class)
                    ->options(['container_return_id' => $this->ownerRecord->getKey()])
                    ->authorize(fn (): bool => $this->canCreateEntry()),
                CreateAction::make()
                    ->schema($this->entrySchema())
                    ->using(fn (array $data): ContainerReturnEntry => app(AddContainerReturnEntryAction::class)
                        ->handle($this->ownerRecord, $this->entryData($data)))
                    ->createAnother(false)
                    ->authorize(fn (): bool => $this->canCreateEntry()),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema($this->entrySchema())
                    ->iconButton()
                    ->using(
                        fn (ContainerReturnEntry $record, array $data): ContainerReturnEntry => app(
                            UpdateContainerReturnEntryAction::class,
                        )->handle($record, $this->entryData($data)),
                    )
                    ->authorize(
                        fn (ContainerReturnEntry $record): bool => auth()->user()?->can('update', $record) ?? false,
                    ),
                DeleteAction::make()
                    ->iconButton()
                    ->using(function (ContainerReturnEntry $record): bool {
                        app(DeleteContainerReturnEntryAction::class)->handle($record);

                        return true;
                    })
                    ->authorize(
                        fn (ContainerReturnEntry $record): bool => auth()->user()?->can('delete', $record) ?? false,
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContainerReturnEntryExporter::class),
                ]),
            ]);
    }

    /**
     * @return array<int, Select|Textarea>
     */
    private function entrySchema(): array
    {
        return [
            Select::make('container_id')
                ->relationship(
                    name: 'container',
                    titleAttribute: 'serial',
                    modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->whereIn('id', Container::query()->currentlyDispatched()->select('id'))
                        ->orderBy('serial'),
                )
                ->searchable(['serial', 'name'])
                ->required(),
            Select::make('return_condition')
                ->options(ReturnCondition::class)
                ->default(ReturnCondition::Good)
                ->required(),
            Textarea::make('note')
                ->maxLength(2000)
                ->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function entryData(array $data): AddContainerReturnEntryData
    {
        return new AddContainerReturnEntryData(
            containerId: $data['container_id'],
            condition: $data['return_condition'],
            note: $data['note'] ?? null,
        );
    }

    private function canCreateEntry(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('create', ContainerReturnEntry::class) && $user->can('update', $this->ownerRecord);
    }
}
