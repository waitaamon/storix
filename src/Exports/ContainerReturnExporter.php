<?php

declare(strict_types=1);

namespace Storix\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Storix\Models\ContainerReturn;

final class ContainerReturnExporter extends Exporter
{
    protected static ?string $model = ContainerReturn::class;

    /** Define the columns to include in the export. */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('transaction_date'),
            ExportColumn::make('customer.'.(string) config('storix.customer_title_attribute', 'name'))->label('Customer'),
            ExportColumn::make('items_count')->counts('items')->label('Containers'),
            ExportColumn::make('notes'),
        ];
    }

    /** Get the notification body shown after a successful export. */
    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf('Return export completed. %d row(s) exported.', $export->successful_rows);
    }
}
