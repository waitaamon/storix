<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;
use WaitAmon\Storix\Models\Dispatch;
use WaitAmon\Storix\Support\TableNames;

final class DispatchReturnImporter extends Importer
{
    protected static ?string $model = Dispatch::class;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        $usersTable = TableNames::users();

        return [
            ImportColumn::make('id')
                ->requiredMapping()
                ->rules(['required', 'uuid']),
            ImportColumn::make('received_by')
                ->requiredMapping()
                ->rules(['required', 'uuid', 'exists:'.$usersTable.',id']),
            ImportColumn::make('return_date')
                ->rules(['nullable', 'date']),
            ImportColumn::make('return_condition')
                ->requiredMapping()
                ->rules(['required', 'in:good,damaged,lost']),
            ImportColumn::make('return_note')
                ->rules(['nullable', 'string']),
        ];
    }

    public function resolveRecord(): Dispatch
    {
        $dispatch = Dispatch::query()->find((string) $this->data['id']);

        if ($dispatch instanceof Dispatch) {
            return $dispatch;
        }

        throw ValidationException::withMessages([
            'id' => sprintf('Dispatch %s was not found.', (string) $this->data['id']),
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return sprintf(
            'Return import finished: %d successful rows, %d failed rows.',
            $import->successful_rows,
            $import->getFailedRowsCount(),
        );
    }
}
