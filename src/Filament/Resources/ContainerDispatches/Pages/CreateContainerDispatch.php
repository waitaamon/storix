<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Resources\ContainerDispatches\Pages;

use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use Storix\ContainerMovement\DTOs\DispatchContainerDTO;
use Storix\ContainerMovement\Filament\Resources\ContainerDispatches\ContainerDispatchResource;
use Storix\ContainerMovement\Models\Container;
use Storix\ContainerMovement\Services\ContainerDispatchService;

final class CreateContainerDispatch extends CreateRecord
{
    protected static string $resource = ContainerDispatchResource::class;

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
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Dispatch failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            throw $exception;
        }
    }
}
