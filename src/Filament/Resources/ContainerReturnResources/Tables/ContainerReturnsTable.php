<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources\Tables;

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
use Storix\Filament\Exports\ContainerReturnExporter;
use Storix\Models\ContainerReturn;

final class ContainerReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['customer', 'user', 'approvedBy'])
                ->withCount('entries')
                ->orderByDesc('transaction_date')
                ->orderByDesc('id'))
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('entries_count')
                    ->label('Entries')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Prepared By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('state'),
            ])
            ->filters([
                SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('state')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Submitted',
                        'approved' => 'Approved',
                    ]),

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
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate(
                                'transaction_date',
                                '>=',
                                $date,
                            ),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn (Builder $query, string $date): Builder => $query->whereDate(
                                'transaction_date',
                                '<=',
                                $date,
                            ),
                        )),
            ])
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->authorize(fn (ContainerReturn $record): bool => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()
                    ->iconButton()
                    ->authorize(fn (ContainerReturn $record): bool => auth()->user()?->can('update', $record) ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContainerReturnExporter::class),
                ]),
            ]);
    }
}
