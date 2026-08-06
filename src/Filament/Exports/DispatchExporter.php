<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Override;
use Storix\Models\Dispatch;
use Storix\Support\SpreadsheetSafeText;

final class DispatchExporter extends Exporter
{
    #[Override]
    protected static ?string $model = Dispatch::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('customer.name')
                ->label('Customer')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('deliveryNote.code')
                ->label('Delivery note')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('quantity'),
            ExportColumn::make('dispatchedBy.name')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('dispatch_note')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('dispatched_at'),
            ExportColumn::make('approved_at'),
            ExportColumn::make('approvedBy.name')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('voided_at'),
            ExportColumn::make('voidedBy.name')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('state'),
        ];
    }

    /**
     * @param  Builder<Dispatch>  $query
     * @return Builder<Dispatch>
     */
    #[Override]
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'customer',
            'deliveryNote',
            'dispatchedBy',
            'approvedBy',
            'voidedBy',
        ]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf(
            'Dispatch export finished: %d successful rows, %d failed rows.',
            $export->successful_rows,
            $export->getFailedRowsCount(),
        );
    }
}
