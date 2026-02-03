<?php

declare(strict_types=1);

namespace Storix\Imports;

use Carbon\CarbonImmutable;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
            ImportColumn::make('container_serial')->requiredMapping()->rules(['required', 'string']),
            ImportColumn::make('return_date')->requiredMapping()->rules(['required', 'date']),
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
            throw new StorixException(sprintf('Container [%s] does not exist.', (string) ($row['container_serial'] ?? '')));
        }

        $customerId = DB::table((string) config('storix.tables.dispatch_items', 'container_dispatch_items').' as i')
            ->join((string) config('storix.tables.dispatches', 'container_dispatches').' as d', 'd.id', '=', 'i.dispatch_id')
            ->leftJoin((string) config('storix.tables.return_items', 'container_return_items').' as r', 'r.dispatch_item_id', '=', 'i.id')
            ->whereNull('r.id')
            ->where('i.container_id', $container->getKey())
            ->orderByDesc('i.id')
            ->value('d.customer_id');

        if (! is_numeric($customerId)) {
            throw new StorixException(sprintf('Container [%s] is not currently dispatched.', $container->serial));
        }

        $service = app(ContainerReturnService::class);

        return $service->return(new ReturnContainerDTO(
            customerId: (int) $customerId,
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

            return self::importRow((array) $this->data, is_numeric($userId) ? (int) $userId : null);
        } catch (StorixException $exception) {
            $this->fail('container_serial', $exception->getMessage());
        }

        return null;
    }
}
