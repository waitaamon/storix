<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use WaitAmon\Storix\Models\Container;

final class ContainerExporter extends Exporter
{
    protected static ?string $model = Container::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('name'),
            ExportColumn::make('serial'),
            ExportColumn::make('is_active'),
            ExportColumn::make('description'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody($export): string
    {
        return sprintf('Container export completed with %d successful rows.', $export->successful_rows);
    }
}
