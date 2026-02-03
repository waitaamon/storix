<?php

declare(strict_types=1);

namespace Storix\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Storix\Models\Container;

final class ContainerExporter extends Exporter
{
    protected static ?string $model = Container::class;

    /** Define the columns to include in the export. */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('serial'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('created_at'),
        ];
    }

    /** Get the notification body shown after a successful export. */
    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf('Container export completed. %d row(s) exported.', $export->successful_rows);
    }
}
