<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Imports;

use Carbon\CarbonImmutable;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Storix\ContainerMovement\DTOs\ReturnContainerDTO;
use Storix\ContainerMovement\DTOs\ReturnContainerItemDTO;
use Storix\ContainerMovement\Enums\ContainerConditionStatus;
use Storix\ContainerMovement\Exceptions\ContainerMovementException;
use Storix\ContainerMovement\Models\Container;
use Storix\ContainerMovement\Models\ContainerReturn;
use Storix\ContainerMovement\Services\ContainerReturnService;

final class ReturnImporter extends Importer
{
    protected static ?string $model = Model::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('container_serial')->requiredMapping()->rules(['required', 'string']),
            ImportColumn::make('return_date')->requiredMapping()->rules(['required', 'date']),
            ImportColumn::make('condition_status')->requiredMapping()->rules(['required', 'string']),
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
    public static function importRow(array $row, ?int $userId = null): ContainerReturn
    {
        $container = Container::query()
            ->where('serial', trim((string) ($row['container_serial'] ?? '')))
            ->first();

        if (! $container instanceof Container) {
            throw new ContainerMovementException(sprintf('Container [%s] does not exist.', (string) ($row['container_serial'] ?? '')));
        }

        $customerId = DB::table((string) config('container-movement.tables.dispatch_items', 'container_dispatch_items').' as i')
            ->join((string) config('container-movement.tables.dispatches', 'container_dispatches').' as d', 'd.id', '=', 'i.dispatch_id')
            ->leftJoin((string) config('container-movement.tables.return_items', 'container_return_items').' as r', 'r.dispatch_item_id', '=', 'i.id')
            ->whereNull('r.id')
            ->where('i.container_id', $container->getKey())
            ->orderByDesc('i.id')
            ->value('d.customer_id');

        if (! is_int($customerId)) {
            throw new ContainerMovementException(sprintf('Container [%s] is not currently dispatched.', $container->serial));
        }

        $service = app(ContainerReturnService::class);

        return $service->return(new ReturnContainerDTO(
            customerId: $customerId,
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

    public static function getCompletedNotificationBody(Import $import): string
    {
        return sprintf('Return import completed. %d row(s) processed.', $import->successful_rows);
    }
}
