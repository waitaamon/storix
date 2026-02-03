<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerDispatches\Pages;

use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Storix\DTOs\DispatchContainerDTO;
use Storix\Exceptions\StorixException;
use Storix\Filament\Resources\ContainerDispatches\ContainerDispatchResource;
use Storix\Models\Container;
use Storix\Services\ContainerDispatchService;

final class CreateContainerDispatch extends CreateRecord
{
    protected static string $resource = ContainerDispatchResource::class;

    /**
     * Convert selected container IDs to serials and dispatch via the service layer.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $service = app(ContainerDispatchService::class);

        $containerIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            (array) ($data['container_ids'] ?? []),
        ))));

        $serials = Container::query()
            ->whereIn('id', $containerIds)
            ->pluck('serial')
            ->all();

        try {
            return $service->dispatch(new DispatchContainerDTO(
                customerId: (int) $data['customer_id'],
                saleOrderCode: (string) $data['sale_order_code'],
                transactionDate: CarbonImmutable::parse((string) $data['transaction_date']),
                containerSerials: array_values(array_map(static fn (mixed $serial): string => (string) $serial, $serials)),
                notes: isset($data['notes']) ? (string) $data['notes'] : null,
                userId: is_numeric(auth()->id()) ? (int) auth()->id() : null,
            ));
        } catch (StorixException $exception) {
            Notification::make()
                ->title('Dispatch failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->halt();

            return new \Storix\Models\ContainerDispatch();
        }
    }
}
