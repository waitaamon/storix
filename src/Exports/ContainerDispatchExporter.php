<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Storix\ContainerMovement\Models\ContainerDispatch;

final class ContainerDispatchExporter extends Exporter
{
    protected static ?string $model = ContainerDispatch::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('transaction_date'),
            ExportColumn::make('sale_order_code'),
            ExportColumn::make('customer.name')->label('Customer'),
            ExportColumn::make('items_count')->counts('items')->label('Containers'),
            ExportColumn::make('notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf('Dispatch export completed. %d row(s) exported.', $export->successful_rows);
    }
}
