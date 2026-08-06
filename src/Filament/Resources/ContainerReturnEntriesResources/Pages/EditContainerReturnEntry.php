<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnEntriesResources\Pages;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;
use Storix\Actions\DeleteContainerReturnEntryAction;
use Storix\Actions\UpdateContainerReturnEntryAction;
use Storix\Data\AddContainerReturnEntryData;
use Storix\Filament\Resources\ContainerReturnEntriesResources\ContainerReturnEntryResource;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Models\ContainerReturnEntry;

final class EditContainerReturnEntry extends EditRecord
{
    #[Override]
    protected static string $resource = ContainerReturnEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ContainerReturnEntry $record */
        return app(UpdateContainerReturnEntryAction::class)->handle(
            $record,
            new AddContainerReturnEntryData(
                containerId: $data['container_id'],
                condition: $data['return_condition'],
                note: $data['note'] ?? null,
            ),
        );
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make()
                    ->authorize(
                        fn (ContainerReturnEntry $record): bool => auth()->user()?->can('delete', $record) ?? false,
                    )
                    ->using(function (ContainerReturnEntry $record): bool {
                        app(DeleteContainerReturnEntryAction::class)->handle($record);

                        return true;
                    }),
            ]),
        ];
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        /** @var ContainerReturnEntry $entry */
        $entry = $this->getRecord();

        return ContainerReturnResource::getUrl('view', ['record' => $entry->containerReturn]);
    }
}
