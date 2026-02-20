<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntriesResources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Enums\ReturnCondition;
use Storix\Support\FinancialYear;
use Storix\Filament\Exports\DispatchEntryExporter;
use Storix\Filament\Imports\DispatchReturnImporter;
use Storix\Models\Container;
use Storix\Models\DispatchEntry;
use UnitEnum;

final class DispatchEntryResource extends Resource
{
    protected static ?string $model = DispatchEntry::class;

    protected static string|UnitEnum|null $navigationGroup = 'Storix';

    #[Override]
    public static function getModelLabel(): string
    {
        return Config::string('storix.labels.dispatch_entry');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        $year = FinancialYear::selected();

        return $schema
            ->components([
                DatePicker::make('return_date')
                    ->native(false)
                    ->minDate($year->start_date ?? today())
                    ->maxDate($year->end_date ?? today())
                    ->closeOnDateSelection()
                    ->required(),
                Select::make('return_condition')
                    ->options(ReturnCondition::class)
                    ->native(false)
                    ->required(),
                Textarea::make('return_note')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dispatch.code')
                    ->label('Code')
                    ->searchable(),

                TextColumn::make('container.name')
                    ->searchable()
                    ->label('Name')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('container.serial')
                    ->searchable()
                    ->label('Serial'),

                TextColumn::make('dispatch.deliveryNote.customer.name')
                    ->searchable()
                    ->label('Customer'),

                TextColumn::make('dispatch.deliveryNote.code')
                    ->searchable()
                    ->label('Delivery Note'),

                TextColumn::make('dispatch.dispatched_at')
                    ->date()
                    ->label('Date'),

                TextColumn::make('dispatchedBy.name')
                    ->label('Dispatched By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('return_date')
                    ->date(),

                TextColumn::make('return_condition')
                    ->badge()
                    ->label('Return Condition'),

                TextColumn::make('receivedBy.name')
                    ->label('Received By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->modalHeading('Update Dispatch Entry')
                    ->authorize(fn () => auth()->user()->can('receive', Container::class)),
            ])
            ->toolbarActions([
                ImportAction::make('Bulk Returns Import')
                    ->icon('heroicon-o-document-arrow-up')
                    ->outlined()
                    ->color('primary')
                    ->size(Size::ExtraSmall)
                    ->label('Import Returns')
                    ->importer(DispatchReturnImporter::class),

                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchEntryExporter::class),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDispatchEntries::route('/'),
        ];
    }
}
