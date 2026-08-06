<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Override;
use Storix\Models\ContainerReturnEntry;
use Storix\Support\SpreadsheetSafeText;

final class ContainerReturnEntryExporter extends Exporter
{
    #[Override]
    protected static ?string $model = ContainerReturnEntry::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('containerReturn.code')
                ->label('Return code')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('containerReturn.customer.name')
                ->label('Returning customer')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('containerReturn.transaction_date')
                ->label('Return date'),
            ExportColumn::make('containerReturn.state')
                ->label('Return state'),
            ExportColumn::make('container.serial')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('container.name')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('return_condition'),
            ExportColumn::make('dispatchEntry.dispatch.code')
                ->label('Source dispatch')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('dispatchEntry.dispatch.customer.name')
                ->label('Source customer')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('cross_return'),
            ExportColumn::make('note')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
        ];
    }

    /**
     * @param  Builder<ContainerReturnEntry>  $query
     * @return Builder<ContainerReturnEntry>
     */
    #[Override]
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'container',
            'containerReturn.customer',
            'dispatchEntry.dispatch.customer',
        ]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf(
            'Container return entry export finished: %d successful rows, %d failed rows.',
            $export->successful_rows,
            $export->getFailedRowsCount(),
        );
    }
}
