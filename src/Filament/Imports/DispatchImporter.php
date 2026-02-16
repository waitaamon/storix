<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
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
        $usersTable = TableNames::users();

        return [
            ImportColumn::make('container_id')
                ->requiredMapping()
                ->rules(['required', 'integer', 'exists:'.$containersTable.',id']),

            ImportColumn::make('customer_id')
                ->requiredMapping()
                ->rules(['required', 'integer', 'exists:'.$customersTable.',id']),

            ImportColumn::make('dispatched_by')
                ->requiredMapping()
                ->rules(['required', 'integer', 'exists:'.$usersTable.',id']),

            ImportColumn::make('delivery_note')
                ->rules(['required', 'string']),

            ImportColumn::make('dispatched_note')
                ->rules(['nullable', 'string']),

            ImportColumn::make('dispatched_at')
                ->rules(['required', 'date']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return sprintf(
            'Dispatch import finished: %d successful rows, %d failed rows.',
            $import->successful_rows,
            $import->getFailedRowsCount(),
        );
    }

    public function resolveRecord(): Dispatch
    {
        return new Dispatch();
    }
}
