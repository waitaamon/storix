<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Filament\Resources\DispatchResources\Pages\CreateDispatch;
use Storix\Filament\Resources\DispatchResources\Pages\EditDispatch;
use Storix\Filament\Resources\DispatchResources\Pages\ListDispatches;
use Storix\Filament\Resources\DispatchResources\Pages\ViewDispatch;
use Storix\Filament\Resources\DispatchResources\RelationManagers\ContainersRelationManager;
use Storix\Filament\Resources\DispatchResources\Schemas\DispatchForm;
use Storix\Filament\Resources\DispatchResources\Schemas\DispatchInfolist;
use Storix\Filament\Resources\DispatchResources\Tables\DispatchesTable;
use Storix\Models\Dispatch;
use UnitEnum;

final class DispatchResource extends Resource
{
    #[Override]
    protected static ?string $model = Dispatch::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Storix';

    #[Override]
    public static function getModel(): string
    {
        $model = Config::string('storix.models.dispatch', Dispatch::class);

        return is_a($model, Model::class, true) ? $model : Dispatch::class;
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return Config::string('storix.labels.dispatch');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return DispatchForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return DispatchesTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return DispatchInfolist::configure($schema);
    }

    /**
     * @return array<class-string<RelationManager>>
     */
    #[Override]
    public static function getRelations(): array
    {
        return [
            ContainersRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListDispatches::route('/'),
            'create' => CreateDispatch::route('/create'),
            'view' => ViewDispatch::route('/{record}'),
            'edit' => EditDispatch::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
