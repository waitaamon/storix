<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Override;
use Storix\Models\DispatchEntry;
use Storix\Support\SpreadsheetSafeText;

final class DispatchEntryExporter extends Exporter
{
    #[Override]
    protected static ?string $model = DispatchEntry::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('container.serial')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('container.name')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('container.replacement_cost'),
            ExportColumn::make('container.replacement_currency'),
            ExportColumn::make('dispatch.customer.name')
                ->label('Customer')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('dispatch.deliveryNote.code')
                ->label('Delivery note')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('dispatch.dispatched_at'),
            ExportColumn::make('dispatch.dispatch_note')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
        ];
    }

    /**
     * @param  Builder<DispatchEntry>  $query
     * @return Builder<DispatchEntry>
     */
    #[Override]
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'container',
            'dispatch.customer',
            'dispatch.deliveryNote',
        ]);
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
