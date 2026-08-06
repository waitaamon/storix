<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Tables;

use Carbon\CarbonImmutable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Storix\Filament\Exports\DispatchExporter;
use Storix\Models\Dispatch;
use Storix\Support\FinancialYear;

final class DispatchesTable
{
    public static function configure(Table $table): Table
    {
        $year = FinancialYear::selected();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($year): Builder {
                $query->with([
                    'customer',
                    'deliveryNote',
                    'dispatchedBy',
                ]);

                if ($year) {
                    $query->whereBetween('dispatched_at', [$year->start_date, $year->end_date]);
                }

                return $query->orderByDesc('dispatched_at');
            })
            ->columns([
                TextColumn::make('code')
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('deliveryNote.code')
                    ->label('Delivery Note')
                    ->searchable(),

                TextColumn::make('dispatched_at')
                    ->date()
                    ->sortable(),

                TextColumn::make('dispatchedBy.name')
                    ->label('Dispatched By'),

                TextColumn::make('quantity')
                    ->sortable(),

                TextColumn::make('containers_count')
                    ->label(fn (): string => str(Config::string('storix.labels.container'))->plural()->headline().' count')
                    ->counts('containers'),

                TextColumn::make('state')
                    ->icon(false),
            ])
            ->filters([
                SelectFilter::make('customer')
                    ->relationship('customer', 'name')
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
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->where(
                                'approved_at',
                                '>=',
                                CarbonImmutable::parse($date)->startOfDay(),
                            ),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->where(
                                'approved_at',
                                '<',
                                CarbonImmutable::parse($date)->addDay()->startOfDay(),
                            ),
                        ))
                    ->indicateUsing(fn (array $data): array => self::dateRangeIndicators($data, 'Dispatch date')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->authorize(fn (Dispatch $record) => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()
                    ->iconButton()
                    ->authorize(fn (Dispatch $record) => auth()->user()?->can('update', $record) ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(DispatchExporter::class),
                ]),
            ]);
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
