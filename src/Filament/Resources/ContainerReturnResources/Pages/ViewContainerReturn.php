<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use LogicException;
use Override;
use Storix\Actions\ApproveContainerReturnAction;
use Storix\Actions\DeleteContainerReturnAction;
use Storix\Actions\ReturnContainerReturnToDraftAction;
use Storix\Actions\SubmitContainerReturnAction;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Models\ContainerReturn;

final class ViewContainerReturn extends ViewRecord
{
    #[Override]
    protected static string $resource = ContainerReturnResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit')
                ->icon('heroicon-o-paper-airplane')
                ->authorize(
                    fn (ContainerReturn $record): bool => auth()->user()?->can('submit', $record) ?? false,
                )
                ->action(
                    fn (ContainerReturn $record): ContainerReturn => app(SubmitContainerReturnAction::class)
                        ->handle($record),
                )
                ->successRedirectUrl(fn (ContainerReturn $record): string => $this->recordUrl($record))
                ->requiresConfirmation(),

            Action::make('returnToDraft')
                ->label('Return to Draft')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->authorize(fn (ContainerReturn $record): bool => auth()->user()?->can('draft', $record) ?? false)
                ->action(fn (ContainerReturn $record): ContainerReturn => app(ReturnContainerReturnToDraftAction::class)->handle($record))
                ->successRedirectUrl(fn (ContainerReturn $record): string => $this->recordUrl($record))
                ->requiresConfirmation(),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->authorize(fn (ContainerReturn $record): bool => auth()->user()?->can('approve', $record) ?? false)
                ->action(fn (ContainerReturn $record): ContainerReturn => app(ApproveContainerReturnAction::class)->handle($record, $this->authenticatedUserId()))
                ->successRedirectUrl(fn (ContainerReturn $record): string => $this->recordUrl($record))
                ->requiresConfirmation(),

            ActionGroup::make([
                EditAction::make()
                    ->authorize(fn (ContainerReturn $record): bool => auth()->user()?->can('update', $record) ?? false),
                DeleteAction::make()
                    ->authorize(fn (ContainerReturn $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->using(function (ContainerReturn $record): bool {
                        app(DeleteContainerReturnAction::class)->handle($record);

                        return true;
                    }),
            ]),
        ];
    }

    private function recordUrl(ContainerReturn $record): string
    {
        return ContainerReturnResource::getUrl('view', ['record' => $record]);
    }

    private function authenticatedUserId(): int|string
    {
        $userId = auth()->id();

        if (! is_int($userId) && ! is_string($userId)) {
            throw new LogicException('An authenticated user is required to approve a container return.');
        }

        return $userId;
    }
}
