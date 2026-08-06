<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Override;
use Storix\Actions\CreateContainerReturnAction;
use Storix\Data\CreateContainerReturnData;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;

final class CreateContainerReturn extends CreateRecord
{
    #[Override]
    protected static string $resource = ContainerReturnResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $userId = Filament::auth()->id();

        if (! is_int($userId) && ! is_string($userId)) {
            throw new LogicException('An authenticated user is required to prepare a container return.');
        }

        return app(CreateContainerReturnAction::class)->handle(new CreateContainerReturnData(
            customerId: $data['customer_id'],
            userId: $userId,
            transactionDate: $data['transaction_date'],
            note: $data['note'] ?? null,
        ));
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return ContainerReturnResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
