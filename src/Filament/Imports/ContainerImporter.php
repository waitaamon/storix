<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
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
            ImportColumn::make('description')
                ->rules(['nullable', 'string']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return sprintf(
            'Container import finished: %d successful rows, %d failed rows.',
            $import->successful_rows,
            $import->getFailedRowsCount(),
        );
    }

    public function resolveRecord(): ?Container
    {
        return Container::query()->firstOrNew([
            'serial' => (string) $this->data['serial'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidationAttributes(): array
    {
        return [
            'name' => 'container name',
            'serial' => 'container serial',
        ];
    }
}
