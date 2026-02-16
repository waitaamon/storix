<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Resources;

use Filament\Actions\Exports\ExportAction;
use Filament\Actions\Exports\ExportBulkAction;
use Filament\Actions\Imports\ImportAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use WaitAmon\Storix\Enums\DispatchStatus;
use WaitAmon\Storix\Filament\Exports\DispatchExporter;
use WaitAmon\Storix\Filament\Imports\DispatchImporter;
use WaitAmon\Storix\Filament\Imports\DispatchReturnImporter;
use WaitAmon\Storix\Filament\Resources\DispatchResource\Pages;
use WaitAmon\Storix\Models\Dispatch;

final class DispatchResource extends Resource
{
    protected static ?string $model = Dispatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Storix';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Dispatch')
                    ->schema([
                        Select::make('container_id')
                            ->relationship('container', 'serial')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('customer_id')
                            ->required()
                            ->uuid(),
                        TextInput::make('dispatched_by')
                            ->required()
                            ->uuid(),
                        Textarea::make('delivery_note'),
                        DateTimePicker::make('dispatched_at')
                            ->required(),
                        Textarea::make('dispatched_note'),
                    ])
                    ->columns(2),
                Section::make('Return Tracking')
                    ->schema([
                        Placeholder::make('status_indicator')
                            ->label('Current Status')
                            ->content(fn (?Dispatch $record): string => $record?->status->label() ?? DispatchStatus::Dispatched->label()),
                        TextInput::make('received_by')->uuid(),
                        DateTimePicker::make('return_date'),
                        Select::make('return_condition')
                            ->options([
                                'good' => 'Returned Good',
                                'damaged' => 'Returned Damaged',
                                'lost' => 'Lost',
                            ])
                            ->native(false),
                        Textarea::make('return_note')->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('container.serial')->label('Container')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer_id')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('dispatched_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('return_date')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DispatchStatus $state): string => $state->label())
                    ->color(fn (DispatchStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('return_condition')
                    ->options([
                        'good' => 'Returned Good',
                        'damaged' => 'Returned Damaged',
                        'lost' => 'Lost',
                    ]),
                TrashedFilter::make(),
            ])
            ->headerActions([
                ImportAction::make()
                    ->label('Import Dispatches')
                    ->importer(DispatchImporter::class),
                ImportAction::make('importReturns')
                    ->label('Import Returns')
                    ->importer(DispatchReturnImporter::class),
                ExportAction::make()->exporter(DispatchExporter::class),
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
                    ExportBulkAction::make()->exporter(DispatchExporter::class),
                ]),
            ]);
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
