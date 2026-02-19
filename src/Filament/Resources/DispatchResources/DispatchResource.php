<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources;

use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Filament\Resources\DispatchResources\RelationManagers\DispatchEntriesRelationManager;
use Storix\Filament\Resources\DispatchResources\Schemas\DispatchForm;
use Storix\Filament\Resources\DispatchResources\Schemas\DispatchInfolist;
use Storix\Filament\Resources\DispatchResources\Tables\DispatchesTable;
use Storix\Models\Dispatch;
use UnitEnum;

final class DispatchResource extends Resource
{
    protected static ?string $model = Dispatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Storix';

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
    public static function getRelations(): array
    {
        return [
            DispatchEntriesRelationManager::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDispatches::route('/'),
            'create' => Pages\CreateDispatch::route('/create'),
            'view' => Pages\ViewDispatch::route('/{record}'),
            'edit' => Pages\EditDispatch::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
