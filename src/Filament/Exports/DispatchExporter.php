<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use WaitAmon\Storix\Models\Dispatch;

final class DispatchExporter extends Exporter
{
    protected static ?string $model = Dispatch::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
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

    public static function getCompletedNotificationBody($export): string
    {
        return sprintf('Dispatch export completed with %d successful rows.', $export->successful_rows);
    }
}
