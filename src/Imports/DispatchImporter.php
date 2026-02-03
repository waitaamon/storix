<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Imports;

use Carbon\CarbonImmutable;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Storix\ContainerMovement\DTOs\DispatchContainerDTO;
use Storix\ContainerMovement\Exceptions\ContainerMovementException;
use Storix\ContainerMovement\Models\ContainerDispatch;
use Storix\ContainerMovement\Services\ContainerDispatchService;

final class DispatchImporter extends Importer
{
    protected static ?string $model = Model::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('container_serial')->requiredMapping()->rules(['required', 'string']),
            ImportColumn::make('customer_name')->requiredMapping()->rules(['required', 'string']),
            ImportColumn::make('sale_order_code')->requiredMapping()->rules(['required', 'string']),
            ImportColumn::make('dispatch_date')->requiredMapping()->rules(['required', 'date']),
            ImportColumn::make('notes')->rules(['nullable', 'string']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        try {
            $userId = auth()->id();

            return self::importRow((array) $this->data, is_numeric($userId) ? (int) $userId : null);
        } catch (ContainerMovementException $exception) {
            $this->fail('container_serial', $exception->getMessage());
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function importRow(array $row, ?int $userId = null): ContainerDispatch
    {
        $customerClass = (string) config('container-movement.customer_model');

        /** @var Model|null $customer */
        $customer = $customerClass::query()
            ->where('name', trim((string) ($row['customer_name'] ?? '')))
            ->first();

        if (! $customer instanceof Model) {
            throw new ContainerMovementException(sprintf('Customer [%s] does not exist.', (string) ($row['customer_name'] ?? '')));
        }

        $service = app(ContainerDispatchService::class);

        return $service->dispatch(new DispatchContainerDTO(
            customerId: (int) $customer->getKey(),
            saleOrderCode: (string) ($row['sale_order_code'] ?? ''),
            transactionDate: CarbonImmutable::parse((string) ($row['dispatch_date'] ?? '')),
            containerSerials: [(string) ($row['container_serial'] ?? '')],
            notes: isset($row['notes']) ? (string) $row['notes'] : null,
            userId: $userId,
        ));
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return sprintf('Dispatch import completed. %d row(s) processed.', $import->successful_rows);
    }
}
