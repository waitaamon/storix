<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;
use Storix\Actions\CreateDispatchAction;
use Storix\Data\CreateDispatchData;
use Storix\Filament\Resources\DispatchResources\DispatchResource;

final class CreateDispatch extends CreateRecord
{
    #[Override]
    protected static string $resource = DispatchResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $containerIds = array_values($data['container_ids'] ?? []);
        unset($data['container_ids']);

        return app(CreateDispatchAction::class)->handle(new CreateDispatchData(
            deliveryNoteId: $data['delivery_note_id'],
            dispatchedBy: $data['dispatched_by'] ?? Filament::auth()->id(),
            quantity: (int) ($data['quantity'] ?? 1),
            dispatchedAt: $data['dispatched_at'] ?? null,
            dispatchNote: $data['dispatch_note'] ?? null,
            containerIds: $containerIds,
        ));
    }
}
