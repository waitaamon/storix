<?php

declare(strict_types=1);

namespace Storix\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use LogicException;
use Override;

final class CustomerContainerBalanceExporter extends Exporter
{
    /**
     * @return class-string<Model>
     */
    #[Override]
    public static function getModel(): string
    {
        $model = Config::get('storix.models.customer');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new LogicException('The configured Storix customer model must be an Eloquent model class.');
        }

        return $model;
    }

    /**
     * @return array<int, ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('Customer')
                ->preventFormulaInjection(),
            ExportColumn::make('dispatched')
                ->label('Dispatched'),
            ExportColumn::make('returned')
                ->label('Returned'),
            ExportColumn::make('lost')
                ->label('Lost'),
            ExportColumn::make('balance')
                ->label('Balance')
                ->state(static fn (Model $record): int => (int) $record->getRawOriginal('balance')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return sprintf(
            'Customer container balance export finished: %d successful rows, %d failed rows.',
            $export->successful_rows,
            $export->getFailedRowsCount(),
        );
    }
}
