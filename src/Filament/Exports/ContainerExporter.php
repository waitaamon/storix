<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Override;
use Storix\Models\Container;
use Storix\Support\SpreadsheetSafeText;

final class ContainerExporter extends Exporter
{
    #[Override]
    protected static ?string $model = Container::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('serial')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('is_active'),
            ExportColumn::make('replacement_cost'),
            ExportColumn::make('replacement_currency')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('description')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf(
            'Container export finished: %d successful rows, %d failed rows.',
            $export->successful_rows,
            $export->getFailedRowsCount(),
        );
    }
}
