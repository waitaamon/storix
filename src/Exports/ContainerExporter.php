<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Storix\ContainerMovement\Models\Container;

final class ContainerExporter extends Exporter
{
    protected static ?string $model = Container::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name'),
            ExportColumn::make('serial'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf('Container export completed. %d row(s) exported.', $export->successful_rows);
    }
}
