<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Override;
use Storix\Actions\AttachContainersToDispatchAction;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Throwable;

final class DispatchEntryImporter extends Importer
{
    #[Override]
    protected static ?string $model = DispatchEntry::class;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('serial')
                ->requiredMapping()
                ->ignoreBlankState()
                ->rules(['required', 'string']),
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
    #[Override]
    public function resolveRecord(): DispatchEntry
    {
        $container = Container::query()
            ->where('serial', $this->data['serial'])
            ->first();

        if (! $container) {
            throw new RowImportFailedException("No container found with serial [{$this->data['serial']}].");
        }

        return new DispatchEntry([
            'dispatch_id' => $this->options['dispatch_id'],
            'container_id' => $container->getKey(),
        ]);
    }

    /**
     * @throws RowImportFailedException
     * @throws Throwable
     */
    #[Override]
    public function saveRecord(): void
    {
        $containerId = Container::query()
            ->where('serial', $this->data['serial'])
            ->value('id');

        if (! $containerId) {
            throw new RowImportFailedException("No container found with serial [{$this->data['serial']}].");
        }

        app(AttachContainersToDispatchAction::class)->handle(
            Dispatch::query()->whereKey($this->options['dispatch_id'])->firstOrFail(),
            [(int) $containerId],
            checkAvailability: false
        );
    }
}
