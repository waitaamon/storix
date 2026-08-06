<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnEntriesResources\Pages;

use DomainException;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;
use Storix\Actions\AddContainerReturnEntryAction;
use Storix\Data\AddContainerReturnEntryData;
use Storix\Filament\Resources\ContainerReturnEntriesResources\ContainerReturnEntryResource;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;

final class CreateContainerReturnEntry extends CreateRecord
{
    #[Override]
    protected static string $resource = ContainerReturnEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $containerReturnId = $data['container_return_id'];

        if (! is_int($containerReturnId) && ! is_string($containerReturnId)) {
            throw new DomainException('A container return is required.');
        }

        $containerReturn = ContainerReturn::query()
            ->whereKey($containerReturnId)
            ->firstOrFail();

        return app(AddContainerReturnEntryAction::class)->handle(
            $containerReturn,
            new AddContainerReturnEntryData(
                containerId: $data['container_id'],
                condition: $data['return_condition'],
                note: $data['note'] ?? null,
            ),
        );
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        /** @var ContainerReturnEntry $entry */
        $entry = $this->getRecord();

        return ContainerReturnResource::getUrl('view', ['record' => $entry->containerReturn]);
    }
}
