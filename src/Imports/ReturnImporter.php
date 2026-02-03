<?php

declare(strict_types=1);

namespace Storix\Imports;

use Carbon\CarbonImmutable;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Storix\DTOs\ReturnContainerDTO;
use Storix\DTOs\ReturnContainerItemDTO;
use Storix\Enums\ContainerConditionStatus;
use Storix\Exceptions\StorixException;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Services\ContainerReturnService;

final class ReturnImporter extends Importer
{
    protected static ?string $model = Model::class;

    /** Define the expected import columns and their validation rules. */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('customer_name')->requiredMapping()->rules(['required', 'string']),
            ImportColumn::make('transaction_date')->requiredMapping()->rules(['required', 'date']),
            ImportColumn::make('container_serial')->requiredMapping()->rules(['required', 'string']),
            ImportColumn::make('condition_status')->requiredMapping()->rules(['required', 'string']),
            ImportColumn::make('notes')->rules(['nullable', 'string']),
        ];
    }

    /**
     * Import a single return row by looking up the container's open dispatch and recording the return.
     *
     * @param  array<string, mixed>  $row
     *
     * @throws StorixException
     */
    public static function importRow(array $row, ?int $userId = null): ContainerReturn
    {
        $container = Container::query()
            ->where('serial', mb_trim((string) ($row['container_serial'] ?? '')))
            ->first();

        if (! $container instanceof Container) {
            throw new StorixException(sprintf('Container [%s] does not exist.', $row['container_serial'] ?? ''));
        }

        $customerClass = (string) config('storix.customer_model');
        $titleAttribute = (string) config('storix.customer_title_attribute', 'name');

        /** @var Model|null $customer */
        $customer = $customerClass::query()->where($titleAttribute, mb_trim((string) ($row['customer_name'] ?? '')))->first();

        if (! $customer instanceof Model) {
            throw new StorixException(sprintf('Customer [%s] does not exist.', $row['customer_name'] ?? ''));
        }

        $service = app(ContainerReturnService::class);

        return $service->return(new ReturnContainerDTO(
            customerId: (int) $customer->getKey(),
            transactionDate: CarbonImmutable::parse((string) ($row['return_date'] ?? '')),
            items: [
                new ReturnContainerItemDTO(
                    containerSerial: $container->serial,
                    conditionStatus: ContainerConditionStatus::from((string) ($row['condition_status'] ?? ContainerConditionStatus::Good->value)),
                    notes: isset($row['notes']) ? (string) $row['notes'] : null,
                ),
            ],
            notes: isset($row['notes']) ? (string) $row['notes'] : null,
            userId: $userId,
        ));
    }

    /** Get the notification body shown after a successful import. */
    public static function getCompletedNotificationBody(Import $import): string
    {
        return sprintf('Return import completed. %d row(s) processed.', $import->successful_rows);
    }

    /** Resolve or create the return record for a single import row. */
    public function resolveRecord(): ?Model
    {
        try {
            $userId = auth()->id();

            return self::importRow($this->data, is_numeric($userId) ? (int) $userId : null);
        } catch (StorixException $exception) {
            $this->fail('container_serial', $exception->getMessage());
        }

        return null;
    }
}
