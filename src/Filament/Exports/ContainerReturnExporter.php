<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Override;
use Storix\Models\ContainerReturn;
use Storix\Support\SpreadsheetSafeText;

final class ContainerReturnExporter extends Exporter
{
    #[Override]
    protected static ?string $model = ContainerReturn::class;

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('customer.name')
                ->label('Customer')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('transaction_date'),
            ExportColumn::make('state'),
            ExportColumn::make('entries_count')->label('Entry count'),
            ExportColumn::make('user.name')
                ->label('Prepared by')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('approvedBy.name')
                ->label('Approved by')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
            ExportColumn::make('approved_at'),
            ExportColumn::make('note')
                ->formatStateUsing(SpreadsheetSafeText::sanitize(...)),
        ];
    }

    /**
     * @param  Builder<ContainerReturn>  $query
     * @return Builder<ContainerReturn>
     */
    #[Override]
    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->with(['customer', 'user', 'approvedBy'])
            ->withCount('entries');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf(
            'Container return export finished: %d successful rows, %d failed rows.',
            $export->successful_rows,
            $export->getFailedRowsCount(),
        );
    }
}
