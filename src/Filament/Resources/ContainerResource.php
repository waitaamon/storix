<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Resources;

use Filament\Actions\Exports\ExportAction;
use Filament\Actions\Exports\ExportBulkAction;
use Filament\Actions\Imports\ImportAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use WaitAmon\Storix\Filament\Exports\ContainerExporter;
use WaitAmon\Storix\Filament\Imports\ContainerImporter;
use WaitAmon\Storix\Filament\Resources\ContainerResource\Pages;
use WaitAmon\Storix\Filament\Resources\ContainerResource\RelationManagers\DispatchesRelationManager;
use WaitAmon\Storix\Models\Container;

final class ContainerResource extends Resource
{
    protected static ?string $model = Container::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Storix';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('serial')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('serial')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('dispatches_count')
                    ->counts('dispatches')
                    ->label('Dispatches')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('deleted_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                ImportAction::make()->importer(ContainerImporter::class),
                ExportAction::make()->exporter(ContainerExporter::class),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    ExportBulkAction::make()->exporter(ContainerExporter::class),
                ]),
            ]);
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
            'create' => Pages\CreateContainer::route('/create'),
            'view' => Pages\ViewContainer::route('/{record}'),
            'edit' => Pages\EditContainer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
