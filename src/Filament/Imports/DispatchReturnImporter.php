<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Number;
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
        return [
            ImportColumn::make('serial')
                ->relationship('container', resolveUsing: 'serial')
                ->rules(['required', 'string', 'exists:'.TableNames::containers().',serial']),

            ImportColumn::make('return_date')
                ->requiredMapping()
                ->rules(['required', 'date']),

            ImportColumn::make('return_condition')
                ->requiredMapping()
                ->rules(['required', 'in:good,damaged,lost']),

            ImportColumn::make('return_note')
                ->requiredMapping()
                ->rules(['nullable', 'string']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your bulk return import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    /**
     * @throws RowImportFailedException
     */
    public function resolveRecord(): DispatchEntry
    {
        $entry = DispatchEntry::query()
            ->whereHas('container', fn (Builder $query): Builder => $query
                ->where('serial', $this->data['serial'])
                ->currentlyDispatched()
            )
            ->first();

        if (! $entry) {
            throw new RowImportFailedException('No '.str(Config::string('storix.labels.container'))->headline()." found with serial [{$this->data['serial']}].");
        }

        return $entry;
    }
}
