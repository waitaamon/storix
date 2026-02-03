<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturns\Pages;

use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Storix\DTOs\ReturnContainerDTO;
use Storix\DTOs\ReturnContainerItemDTO;
use Storix\Enums\ContainerConditionStatus;
use Storix\Exceptions\StorixException;
use Storix\Filament\Resources\ContainerReturns\ContainerReturnResource;
use Storix\Models\ContainerReturn;
use Storix\Services\ContainerReturnService;

final class CreateContainerReturn extends CreateRecord
{
    protected static string $resource = ContainerReturnResource::class;

    /**
     * Map repeater items to DTOs and record the return via the service layer.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $service = app(ContainerReturnService::class);

        $items = array_map(
            static fn (array $item): ReturnContainerItemDTO => new ReturnContainerItemDTO(
                containerSerial: mb_trim((string) ($item['container_serial'] ?? '')),
                conditionStatus: ContainerConditionStatus::from((string) ($item['condition_status'] ?? ContainerConditionStatus::Good->value)),
                notes: isset($item['notes']) ? (string) $item['notes'] : null,
            ),
            array_values(array_filter(
                (array) ($data['items'] ?? []),
                static fn (array $item): bool => mb_trim((string) ($item['container_serial'] ?? '')) !== '',
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
        } catch (StorixException $exception) {
            Notification::make()
                ->title('Return failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->halt();

            return new ContainerReturn();
        }
    }
}
