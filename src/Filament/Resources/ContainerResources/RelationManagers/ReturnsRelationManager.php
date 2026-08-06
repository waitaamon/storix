<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use Storix\Filament\Exports\ContainerReturnEntryExporter;

final class ReturnsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'returnEntries';

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    #[Override]
    public function isReadOnly(): bool
    {
        return true;
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'containerReturn.customer',
                'dispatchEntry.dispatch',
            ]))
            ->recordTitleAttribute('containerReturn.code')
            ->columns([
                TextColumn::make('containerReturn.code')
                    ->label('Return Code')
                    ->searchable(),
                TextColumn::make('containerReturn.customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('containerReturn.transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('return_condition'),
                TextColumn::make('containerReturn.state')
                    ->label('State'),
                TextColumn::make('dispatchEntry.dispatch.code')
                    ->label('Source Dispatch')
                    ->placeholder('—'),
                IconColumn::make('cross_return')
                    ->boolean(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContainerReturnEntryExporter::class),
                ]),
            ]);
    }
}
