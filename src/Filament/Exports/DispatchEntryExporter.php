<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Storix\Models\DispatchEntry;

final class DispatchEntryExporter extends Exporter
{
    protected static ?string $model = DispatchEntry::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('container.serial'),
            ExportColumn::make('container.name'),
            ExportColumn::make('dispatch.deliveryNote.code')->label('Delivery note'),
            ExportColumn::make('dispatch.dispatched_at'),
            ExportColumn::make('receivedBy.name'),
            ExportColumn::make('return_date'),
            ExportColumn::make('return_condition'),
            ExportColumn::make('return_note'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf(
            'Dispatch entry export finished: %d successful rows, %d failed rows.',
            $export->successful_rows,
            $export->getFailedRowsCount(),
        );
    }
}
