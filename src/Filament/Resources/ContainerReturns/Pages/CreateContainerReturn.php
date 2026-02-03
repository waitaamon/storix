<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Resources\ContainerReturns\Pages;

use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use Storix\ContainerMovement\DTOs\ReturnContainerDTO;
use Storix\ContainerMovement\DTOs\ReturnContainerItemDTO;
use Storix\ContainerMovement\Enums\ContainerConditionStatus;
use Storix\ContainerMovement\Filament\Resources\ContainerReturns\ContainerReturnResource;
use Storix\ContainerMovement\Services\ContainerReturnService;

final class CreateContainerReturn extends CreateRecord
{
    protected static string $resource = ContainerReturnResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $service = app(ContainerReturnService::class);

        $items = array_map(
            static fn (array $item): ReturnContainerItemDTO => new ReturnContainerItemDTO(
                containerSerial: trim((string) ($item['container_serial'] ?? '')),
                conditionStatus: ContainerConditionStatus::from((string) ($item['condition_status'] ?? ContainerConditionStatus::Good->value)),
                notes: isset($item['notes']) ? (string) $item['notes'] : null,
            ),
            array_values(array_filter(
                (array) ($data['items'] ?? []),
                static fn (array $item): bool => trim((string) ($item['container_serial'] ?? '')) !== '',
            )),
        );

        try {
            return $service->return(new ReturnContainerDTO(
                customerId: (int) $data['customer_id'],
                transactionDate: CarbonImmutable::parse((string) $data['transaction_date']),
                items: $items,
                notes: isset($data['notes']) ? (string) $data['notes'] : null,
                userId: is_numeric(auth()->id()) ? (int) auth()->id() : null,
            ));
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Return failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            throw $exception;
        }
    }

}
