<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Filament\Resources\ContainerReturnResources\Pages\CreateContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\Pages\EditContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\Pages\ListContainerReturns;
use Storix\Filament\Resources\ContainerReturnResources\Pages\ViewContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\RelationManagers\EntriesRelationManager;
use Storix\Filament\Resources\ContainerReturnResources\Schemas\ContainerReturnForm;
use Storix\Filament\Resources\ContainerReturnResources\Schemas\ContainerReturnInfolist;
use Storix\Filament\Resources\ContainerReturnResources\Tables\ContainerReturnsTable;
use Storix\Models\ContainerReturn;
use UnitEnum;

final class ContainerReturnResource extends Resource
{
    #[Override]
    protected static ?string $model = ContainerReturn::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Storix';

    #[Override]
    public static function getModel(): string
    {
        $model = Config::string('storix.models.container_return', ContainerReturn::class);

        return is_a($model, Model::class, true) ? $model : ContainerReturn::class;
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return Config::string('storix.labels.container_return');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ContainerReturnForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ContainerReturnsTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return ContainerReturnInfolist::configure($schema);
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    #[Override]
    public static function getRelations(): array
    {
        return [
            EntriesRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListContainerReturns::route('/'),
            'create' => CreateContainerReturn::route('/create'),
            'view' => ViewContainerReturn::route('/{record}'),
            'edit' => EditContainerReturn::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'user', 'approvedBy'])
            ->withCount('entries');
    }
}
