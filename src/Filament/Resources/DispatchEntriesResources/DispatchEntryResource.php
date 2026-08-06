<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntriesResources;

use Carbon\CarbonImmutable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Filament\Exports\DispatchEntryExporter;
use Storix\Filament\Resources\DispatchEntriesResources\Pages\ListDispatchEntries;
use Storix\Filament\Resources\DispatchResources\DispatchResource;
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
        return $schema;
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'container',
                'dispatch.customer',
                'dispatch.deliveryNote',
                'dispatch.dispatchedBy',
            ]))
            ->recordUrl(fn (DispatchEntry $record): string => DispatchResource::getUrl('view', [
                'record' => $record->dispatch,
            ]))
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

                TextColumn::make('dispatch.customer.name')
                    ->searchable()
                    ->label('Customer'),

                TextColumn::make('dispatch.deliveryNote.code')
                    ->searchable()
                    ->placeholder('—')
                    ->label('Delivery Note'),

                TextColumn::make('dispatch.dispatched_at')
                    ->date()
                    ->label('Date'),

                TextColumn::make('dispatch.dispatchedBy.name')
                    ->label('Dispatched By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer')
                    ->relationship('dispatch.customer', 'name')
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchEntryExporter::class),
                ]),
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
