<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Filament\Resources\ContainerResources\Pages\CreateContainer;
use Storix\Filament\Resources\ContainerResources\Pages\EditContainer;
use Storix\Filament\Resources\ContainerResources\Pages\ListContainers;
use Storix\Filament\Resources\ContainerResources\Pages\ViewContainer;
use Storix\Filament\Resources\ContainerResources\RelationManagers\DispatchesRelationManager;
use Storix\Filament\Resources\ContainerResources\RelationManagers\MovementsRelationManager;
use Storix\Filament\Resources\ContainerResources\RelationManagers\ReturnsRelationManager;
use Storix\Filament\Resources\ContainerResources\Schemas\ContainerForm;
use Storix\Filament\Resources\ContainerResources\Schemas\ContainerInfolist;
use Storix\Filament\Resources\ContainerResources\Tables\ContainersTable;
use Storix\Filament\Widgets\ContainerFleetOverviewWidget;
use Storix\Models\Container;
use UnitEnum;

final class ContainerResource extends Resource
{
    #[Override]
    protected static ?string $model = Container::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Storix';

    #[Override]
    public static function getModel(): string
    {
        $model = Config::string('storix.models.container', Container::class);

        return is_a($model, Model::class, true) ? $model : Container::class;
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return Config::string('storix.labels.container');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ContainerForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ContainersTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return ContainerInfolist::configure($schema);
    }

    #[Override]
    public static function getWidgets(): array
    {
        return [
            ContainerFleetOverviewWidget::class,
        ];
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    #[Override]
    public static function getRelations(): array
    {
        return [
            DispatchesRelationManager::class,
            ReturnsRelationManager::class,
            MovementsRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListContainers::route('/'),
            'create' => CreateContainer::route('/create'),
            'view' => ViewContainer::route('/{record}'),
            'edit' => EditContainer::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
