<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerResources\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use Storix\Enums\ContainerMovementType;
use Storix\Filament\Exports\ContainerMovementExporter;

final class MovementsRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'movements';

    #[Override]
    protected static ?string $title = 'Movements';

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('customer'))
            ->recordTitleAttribute('document_code')
            ->defaultSort('movement_date', 'desc')
            ->columns([
                TextColumn::make('movement_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('document_type')
                    ->label('Document')
                    ->badge()
                    ->sortable(),
                TextColumn::make('document_code')
                    ->label('Document Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cross_return')
                    ->label('Cross Return')
                    ->formatStateUsing(static fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('document_type')
                    ->label('Document')
                    ->options(ContainerMovementType::class),
                TernaryFilter::make('cross_return')
                    ->label('Cross Return')
                    ->trueLabel('Cross return')
                    ->falseLabel('Same customer'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ContainerMovementExporter::class),
                ]),
            ]);
    }
}
