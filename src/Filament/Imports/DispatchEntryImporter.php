<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;

final class DispatchEntryImporter extends Importer
{
    protected static ?string $model = Dispatch::class;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('serial')
                ->ignoreBlankState()
                ->relationship(name: 'container', resolveUsing: 'serial'),
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

    /**
     * @throws RowImportFailedException
     */
    public function resolveRecord(): DispatchEntry
    {
        $container = Container::query()
            ->availableForDispatch()
            ->where('serial', $this->data['serial'])
            ->first();

        if (! $container) {
            throw new RowImportFailedException("No container found with serial [{$this->data['serial']}].");
        }

        return new DispatchEntry([
            'dispatch_id' => $this->options['dispatch_id'],
        ]);
    }
}
