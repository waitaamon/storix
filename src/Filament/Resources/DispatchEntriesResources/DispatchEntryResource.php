<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntriesResources;

use Carbon\CarbonImmutable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Actions\ReceiveContainerReturnAction;
use Storix\Data\ReceiveContainerReturnData;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Exports\DispatchEntryExporter;
use Storix\Filament\Imports\DispatchReturnImporter;
use Storix\Filament\Resources\DispatchEntriesResources\Actions\ReceiveSelectedContainersBulkAction;
use Storix\Filament\Resources\DispatchEntriesResources\Pages\ListDispatchEntries;
use Storix\Filament\Resources\DispatchEntriesResources\Schemas\ReceiveContainerReturnForm;
use Storix\Models\DispatchEntry;
use UnitEnum;

final class DispatchEntryResource extends Resource
{
    #[Override]
    protected static ?string $model = DispatchEntry::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Storix';

    #[Override]
    public static function getModel(): string
    {
        $model = Config::string('storix.models.dispatch_entry', DispatchEntry::class);

        return is_a($model, Model::class, true) ? $model : DispatchEntry::class;
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return Config::string('storix.labels.dispatch_entry');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ReceiveContainerReturnForm::configure($schema);
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

                TextColumn::make('dispatch.dispatchedBy.name')
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
            ->filters([
                SelectFilter::make('customer')
                    ->relationship('dispatch.deliveryNote.customer', 'name')
                    ->searchable(),

                Filter::make('approved_at')
                    ->label('Dispatch date')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        if (! $from && ! $until) {
                            return $query;
                        }

                        return $query->whereHas('dispatch', fn (Builder $query): Builder => $query
                            ->when(
                                $from,
                                fn (Builder $query, string $date): Builder => $query->where(
                                    'approved_at',
                                    '>=',
                                    CarbonImmutable::parse($date)->startOfDay(),
                                ),
                            )
                            ->when(
                                $until,
                                fn (Builder $query, string $date): Builder => $query->where(
                                    'approved_at',
                                    '<',
                                    CarbonImmutable::parse($date)->addDay()->startOfDay(),
                                ),
                            ));
                    })
                    ->indicateUsing(fn (array $data): array => self::dateRangeIndicators($data, 'Dispatch date')),

                SelectFilter::make('return_condition')
                    ->label('Condition')
                    ->options(ReturnCondition::class),

                Filter::make('return_date')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->where('return_date', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->where(
                                'return_date',
                                '<',
                                CarbonImmutable::parse($date)->addDay()->toDateString(),
                            ),
                        ))
                    ->indicateUsing(fn (array $data): array => self::dateRangeIndicators($data, 'Return date')),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->modalHeading(fn (): string => 'Receive '.str(Config::string('storix.labels.container'))->headline()->toString())
                    ->mutateRecordDataUsing(fn (array $data): array => [
                        ...$data,
                        'return_date' => $data['return_date'] ?? today(),
                        'return_condition' => $data['return_condition'] ?? ReturnCondition::Good->value,
                    ])
                    ->authorize(fn (DispatchEntry $record) => auth()->user()?->can('receive', $record) ?? false)
                    ->using(fn (DispatchEntry $record, array $data): DispatchEntry => app(ReceiveContainerReturnAction::class)->handle(
                        $record,
                        new ReceiveContainerReturnData(
                            returnDate: $data['return_date'],
                            condition: $data['return_condition'],
                            receivedBy: auth()->id(),
                            note: $data['return_note'] ?? null,
                        ),
                    )),
            ])
            ->toolbarActions([

                BulkActionGroup::make([
                    ReceiveSelectedContainersBulkAction::make(),

                    ExportBulkAction::make()
                        ->exporter(DispatchEntryExporter::class),
                ]),

                ImportAction::make('Bulk Returns Import')
                    ->icon('heroicon-o-document-arrow-up')
                    ->outlined()
                    ->color('primary')
                    ->size(Size::ExtraSmall)
                    ->label('Import Returns')
                    ->importer(DispatchReturnImporter::class),
            ]);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListDispatchEntries::route('/'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function dateRangeIndicators(array $data, string $label): array
    {
        $indicators = [];

        if ($from = $data['from'] ?? null) {
            $indicators['from'] = "{$label} from {$from}";
        }

        if ($until = $data['until'] ?? null) {
            $indicators['until'] = "{$label} until {$until}";
        }

        return $indicators;
    }
}
