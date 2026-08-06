<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Override;
use Storix\Models\ContainerMovement;
use Storix\Support\SpreadsheetSafeText;

final class ContainerMovementExporter extends Exporter
{
    #[Override]
    protected static ?string $model = ContainerMovement::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('container.serial')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('container.name')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('movement_date')
                ->label('Date'),
            ExportColumn::make('customer.name')
                ->label('Customer')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('document_type')
                ->label('Document'),
            ExportColumn::make('document_code')
                ->label('Document code')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('cross_return')
                ->label('Cross return'),
        ];
    }

    /**
     * @param  Builder<ContainerMovement>  $query
     * @return Builder<ContainerMovement>
     */
    #[Override]
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'container',
            'customer',
        ]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf(
            'Container movement export finished: %d successful rows, %d failed rows.',
            $export->successful_rows,
            $export->getFailedRowsCount(),
        );
    }
}
