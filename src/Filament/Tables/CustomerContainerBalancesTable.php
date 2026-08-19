<?php

declare(strict_types=1);

namespace Storix\Filament\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Storix\Filament\Exports\CustomerContainerBalanceExporter;
use Storix\Permissions\StorixPermissions;
use Storix\Support\CustomerContainerBalanceQuery;

final class CustomerContainerBalancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(app(CustomerContainerBalanceQuery::class)->forReport())
            ->modelLabel('customer container balance')
            ->pluralModelLabel('customer container balances')
            ->columns([
                TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dispatched')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('returned')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('lost')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('balance')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(CustomerContainerBalanceExporter::class)
                        ->authorize(fn (): bool => auth()->user()?->can(
                            StorixPermissions::VIEW_ANY_CUSTOMER_CONTAINER_BALANCES,
                        ) ?? false),
                ]),
            ]);
    }
}
