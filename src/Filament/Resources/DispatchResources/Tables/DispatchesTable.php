<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
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
                if ($year) {
                    $query->whereBetween('dispatched_at', [$year->start_date, $year->end_date]);
                }

                return $query->orderByDesc('dispatched_at');
            })
            ->columns([
                TextColumn::make('code')
                    ->searchable(),

                TextColumn::make('deliveryNote.customer.name')
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

                TextColumn::make('containers_count')
                    ->label(fn (): string => str(Config::string('storix.labels.container'))->plural()->headline().' count')
                    ->counts('containers'),

                TextColumn::make('state')
                    ->icon(false),
            ])
            ->filters([
                TrashedFilter::make(),
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
}
