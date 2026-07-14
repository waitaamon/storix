<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use Override;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Storix\Models\Container;

final class ContainerImporter extends Importer
{
    protected static ?string $model = Container::class;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('serial')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('is_active')
                ->boolean()
                ->rules(['nullable', 'boolean']),

            ImportColumn::make('replacement_cost')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('replacement_currency')
                ->rules(['nullable', 'string', 'size:3']),

            ImportColumn::make('description')
                ->rules(['nullable', 'string']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your containers import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    #[Override]
    public function resolveRecord(): Container
    {
        return Container::query()->firstOrNew([
            'serial' => (string) $this->data['serial'],
        ]);
    }
}
