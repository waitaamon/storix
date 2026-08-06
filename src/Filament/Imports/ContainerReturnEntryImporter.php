<?php

declare(strict_types=1);

namespace Storix\Filament\Imports;

use DomainException;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Override;
use Storix\Actions\AddContainerReturnEntryBySerialAction;
use Storix\Data\AddContainerReturnEntryBySerialData;
use Storix\Enums\ReturnCondition;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\States\ContainerReturnDraftState;

final class ContainerReturnEntryImporter extends Importer
{
    #[Override]
    protected static ?string $model = ContainerReturnEntry::class;

    #[Override]
    protected static bool $shouldPreventFormulaInjection = true;

    private ?ContainerReturn $containerReturn = null;

    /**
     * @return array<int, ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('serial')
                ->label('Container Serial')
                ->requiredMapping()
                ->castStateUsing(static fn (mixed $state): string => mb_trim((string) $state))
                ->rules(['required', 'string', 'max:255'])
                ->example('CNT-000123'),
            ImportColumn::make('return_condition')
                ->label('Return Condition')
                ->castStateUsing(static function (mixed $state): ?string {
                    if (blank($state)) {
                        return null;
                    }

                    return str((string) $state)->trim()->lower()->toString();
                })
                ->rules(['nullable', Rule::enum(ReturnCondition::class)])
                ->example(ReturnCondition::Good->value),
            ImportColumn::make('note')
                ->castStateUsing(static fn (mixed $state): ?string => blank($state)
                    ? null
                    : mb_trim((string) $state))
                ->rules(['nullable', 'string', 'max:2000'])
                ->example('Seal inspected on receipt.'),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your container return entries import has completed and '
            .Number::format($import->successful_rows).' '
            .str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '
                .str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    /**
     * @throws RowImportFailedException
     */
    #[Override]
    public function resolveRecord(): ContainerReturnEntry
    {
        $containerReturn = $this->resolveContainerReturn();

        if (! $containerReturn->state->equals(ContainerReturnDraftState::class)) {
            throw new RowImportFailedException(
                'Container return entries can only be imported into a draft container return.',
            );
        }

        $user = auth()->user();

        if ($user === null
            || Gate::forUser($user)->denies('create', ContainerReturnEntry::class)
            || Gate::forUser($user)->denies('update', $containerReturn)) {
            throw new RowImportFailedException(
                'You are not authorized to import entries into this container return.',
            );
        }

        return new ContainerReturnEntry();
    }

    #[Override]
    public function fillRecord(): void
    {
        // The domain action owns all model assignment.
    }

    /**
     * @throws RowImportFailedException
     */
    #[Override]
    public function saveRecord(): void
    {
        try {
            $this->record = app(AddContainerReturnEntryBySerialAction::class)->handle(
                $this->resolveContainerReturn(),
                new AddContainerReturnEntryBySerialData(
                    serial: $this->mappedString('serial'),
                    condition: $this->mappedNullableString('return_condition'),
                    note: $this->mappedNullableString('note'),
                ),
            );
        } catch (DomainException $exception) {
            throw new RowImportFailedException($exception->getMessage(), $exception->getCode(), previous: $exception);
        }
    }

    /**
     * @throws RowImportFailedException
     */
    private function resolveContainerReturn(): ContainerReturn
    {
        if ($this->containerReturn instanceof ContainerReturn) {
            return $this->containerReturn;
        }

        $containerReturnId = $this->options['container_return_id'] ?? null;

        if ((! is_int($containerReturnId) && ! is_string($containerReturnId)) || blank($containerReturnId)) {
            throw new RowImportFailedException('A target container return is required for this import.');
        }

        $containerReturn = ContainerReturn::query()->find($containerReturnId);

        if (! $containerReturn instanceof ContainerReturn) {
            throw new RowImportFailedException('The target container return no longer exists.');
        }

        return $this->containerReturn = $containerReturn;
    }

    private function mappedString(string $column): string
    {
        return (string) ($this->mappedValue($column) ?? '');
    }

    private function mappedNullableString(string $column): ?string
    {
        $value = $this->mappedValue($column);

        return is_string($value) && filled($value) ? $value : null;
    }

    private function mappedValue(string $column): mixed
    {
        if (blank($this->columnMap[$column] ?? null)) {
            return null;
        }

        return $this->data[$column] ?? null;
    }
}
