<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;
use Storix\Filament\Resources\ContainerResources\Pages;
use Storix\Filament\Resources\ContainerResources\RelationManagers\DispatchesRelationManager;
use Storix\Filament\Resources\ContainerResources\Schemas\ContainerForm;
use Storix\Filament\Resources\ContainerResources\Schemas\ContainerInfolist;
use Storix\Filament\Resources\ContainerResources\Tables\ContainersTable;
use Storix\Models\Container;
use UnitEnum;

final class ContainerResource extends Resource
{
    protected static ?string $model = Container::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Storix';

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

    /**
     * @return array<class-string<\Filament\Resources\RelationManagers\RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            DispatchesRelationManager::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContainers::route('/'),
            'view' => Pages\ViewContainer::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
