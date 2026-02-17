<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Storix\Models\Dispatch;
use Storix\Support\TableNames;

final class DispatchImporter extends Importer
{
    protected static ?string $model = Dispatch::class;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        $containersTable = TableNames::containers();
        $customersTable = TableNames::customers();

        return [
            ImportColumn::make('serial')
                ->requiredMapping()
                ->rules(['required', 'string', 'exists:'.$containersTable.',serial']),

            ImportColumn::make('customer')
                ->requiredMapping()
                ->rules(['required', 'string', 'exists:'.$customersTable.',name']),

            ImportColumn::make('delivery_note')
                ->rules(['required', 'string']),

            ImportColumn::make('dispatched_note')
                ->rules(['nullable', 'string']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your dispatch import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    public function resolveRecord(): Dispatch
    {
        return new Dispatch();
    }
}
