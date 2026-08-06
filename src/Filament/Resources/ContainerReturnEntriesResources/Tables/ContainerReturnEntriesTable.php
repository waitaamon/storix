<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnEntriesResources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Exports\ContainerReturnEntryExporter;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Models\ContainerReturnEntry;

final class ContainerReturnEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'container',
                'containerReturn.customer',
                'dispatchEntry.dispatch.customer',
            ]))
            ->recordUrl(fn (ContainerReturnEntry $record): string => ContainerReturnResource::getUrl('view', [
                'record' => $record->containerReturn,
            ]))
            ->columns([
                TextColumn::make('containerReturn.code')
                    ->label('Return Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('containerReturn.customer.name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('containerReturn.transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('container.serial')
                    ->label('Container')
                    ->searchable(),

                TextColumn::make('return_condition'),

                TextColumn::make('dispatchEntry.dispatch.code')
                    ->label('Source Dispatch')
                    ->placeholder('—'),

                IconColumn::make('cross_return')
                    ->boolean(),

                TextColumn::make('containerReturn.state')
                    ->label('State'),
            ])
            ->filters([
                SelectFilter::make('customer')
                    ->relationship('containerReturn.customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('state')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $state): Builder => $query->whereHas(
                            'containerReturn',
                            fn (Builder $query): Builder => $query->where('state', $state),
                        ),
                    )),

                Filter::make('transaction_date')
                    ->label('Transaction date')
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

                        return $query->whereHas('containerReturn', fn (Builder $query): Builder => $query
                            ->when(
                                $from,
                                fn (Builder $query, string $date): Builder => $query->whereDate(
                                    'transaction_date',
                                    '>=',
                                    $date,
                                ),
                            )
                            ->when(
                                $until,
                                fn (Builder $query, string $date): Builder => $query->whereDate(
                                    'transaction_date',
                                    '<=',
                                    $date,
                                ),
                            ));
                    }),

                SelectFilter::make('return_condition')
                    ->options(ReturnCondition::class),

                SelectFilter::make('cross_return')
                    ->options([
                        '1' => 'Cross return',
                        '0' => 'Same customer',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->slideOver()
                    ->authorize(fn (ContainerReturnEntry $record): bool => auth()->user()?->can('update', $record) ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContainerReturnEntryExporter::class),
                ]),
            ]);
    }
}
