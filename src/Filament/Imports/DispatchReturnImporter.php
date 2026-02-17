<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Storix\Models\DispatchEntry;
use Storix\Support\TableNames;

final class DispatchReturnImporter extends Importer
{
    protected static ?string $model = DispatchEntry::class;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        $usersTable = TableNames::users();

        return [
            ImportColumn::make('id')
                ->requiredMapping()
                ->rules(['required', 'integer', 'exists:'.TableNames::dispatchEntries().',id']),

            ImportColumn::make('received_by')
                ->requiredMapping()
                ->rules(['required', 'integer', 'exists:'.$usersTable.',id']),

            ImportColumn::make('return_date')
                ->rules(['required', 'date']),

            ImportColumn::make('return_condition')
                ->requiredMapping()
                ->rules(['required', 'in:good,damaged,lost']),

            ImportColumn::make('return_note')
                ->rules(['nullable', 'string']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return sprintf(
            'Return import finished: %d successful rows, %d failed rows.',
            $import->successful_rows,
            $import->getFailedRowsCount(),
        );
    }

    public function resolveRecord(): DispatchEntry
    {
        return DispatchEntry::query()->findOrFail($this->data['id']);
    }
}
