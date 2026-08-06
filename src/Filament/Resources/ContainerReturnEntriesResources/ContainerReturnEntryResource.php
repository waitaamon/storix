<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnEntriesResources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Filament\Resources\ContainerReturnEntriesResources\Pages\CreateContainerReturnEntry;
use Storix\Filament\Resources\ContainerReturnEntriesResources\Pages\EditContainerReturnEntry;
use Storix\Filament\Resources\ContainerReturnEntriesResources\Pages\ListContainerReturnEntries;
use Storix\Filament\Resources\ContainerReturnEntriesResources\Schemas\ContainerReturnEntryForm;
use Storix\Filament\Resources\ContainerReturnEntriesResources\Tables\ContainerReturnEntriesTable;
use Storix\Models\ContainerReturnEntry;
use UnitEnum;

final class ContainerReturnEntryResource extends Resource
{
    #[Override]
    protected static ?string $model = ContainerReturnEntry::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Storix';

    #[Override]
    public static function getModel(): string
    {
        $model = Config::string('storix.models.container_return_entry', ContainerReturnEntry::class);

        return is_a($model, Model::class, true) ? $model : ContainerReturnEntry::class;
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return Config::string('storix.labels.container_return_entry');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ContainerReturnEntryForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ContainerReturnEntriesTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListContainerReturnEntries::route('/'),
            'create' => CreateContainerReturnEntry::route('/create'),
            'edit' => EditContainerReturnEntry::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'container',
            'containerReturn.customer',
            'dispatchEntry.dispatch.customer',
        ]);
    }
}
