<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Override;
use Storix\Models\Dispatch;

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
            ExportColumn::make('deliveryNote.customer.name')->label('Customer'),
            ExportColumn::make('deliveryNote.code')->label('Delivery note'),
            ExportColumn::make('quantity'),
            ExportColumn::make('dispatchedBy.name'),
            ExportColumn::make('dispatch_note'),
            ExportColumn::make('dispatched_at'),
            ExportColumn::make('approved_at'),
            ExportColumn::make('approvedBy.name'),
            ExportColumn::make('voided_at'),
            ExportColumn::make('voidedBy.name'),
            ExportColumn::make('state'),
        ];
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
