<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Storix\Models\Dispatch;

final class DispatchExporter extends Exporter
{
    protected static ?string $model = Dispatch::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('container.serial'),
            ExportColumn::make('customer_id'),
            ExportColumn::make('dispatched_by'),
            ExportColumn::make('received_by'),
            ExportColumn::make('delivery_note'),
            ExportColumn::make('dispatched_at'),
            ExportColumn::make('return_date'),
            ExportColumn::make('return_condition'),
            ExportColumn::make('return_note'),
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
