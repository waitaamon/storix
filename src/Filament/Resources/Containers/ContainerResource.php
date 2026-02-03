<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\Containers;

use BackedEnum;
use Filament\Actions\Exports\ExportAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Storix\Exports\ContainerExporter;
use Storix\Filament\Resources\Containers\Pages\CreateContainer;
use Storix\Filament\Resources\Containers\Pages\EditContainer;
use Storix\Filament\Resources\Containers\Pages\ListContainers;
use Storix\Models\Container;

final class ContainerResource extends Resource
{
    protected static ?string $model = Container::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    /** Define the container create/edit form. */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('serial')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    /** Define the container listing table. */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('serial')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->headerActions([
                ExportAction::make()->exporter(ContainerExporter::class),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContainers::route('/'),
            'create' => CreateContainer::route('/create'),
            'edit' => EditContainer::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Storix';
    }
}
