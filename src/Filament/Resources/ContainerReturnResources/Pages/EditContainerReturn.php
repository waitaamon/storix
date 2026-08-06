<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources\Pages;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Override;
use Storix\Actions\DeleteContainerReturnAction;
use Storix\Actions\UpdateContainerReturnAction;
use Storix\Data\UpdateContainerReturnData;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Models\ContainerReturn;

final class EditContainerReturn extends EditRecord
{
    #[Override]
    protected static string $resource = ContainerReturnResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ContainerReturn $record */
        return app(UpdateContainerReturnAction::class)->handle($record, new UpdateContainerReturnData(
            customerId: $data['customer_id'],
            transactionDate: $data['transaction_date'],
            note: $data['note'] ?? null,
        ));
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ViewAction::make(),
                DeleteAction::make()
                    ->authorize(
                        fn (ContainerReturn $record): bool => auth()->user()?->can('delete', $record) ?? false,
                    )
                    ->using(function (ContainerReturn $record): bool {
                        app(DeleteContainerReturnAction::class)->handle($record);

                        return true;
                    }),
            ]),
        ];
    }

    #[Override]
    protected function getRedirectUrl(): string
    {
        return ContainerReturnResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
