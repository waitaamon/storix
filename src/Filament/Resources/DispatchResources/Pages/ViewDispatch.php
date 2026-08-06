<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Pages;

use DomainException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Override;
use Storix\Actions\ApproveDispatchAction;
use Storix\Actions\VoidDispatchAction;
use Storix\Data\VoidDispatchData;
use Storix\Filament\Resources\DispatchResources\DispatchResource;
use Storix\Models\Dispatch;

final class ViewDispatch extends ViewRecord
{
    #[Override]
    protected static string $resource = DispatchResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->color('success')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->authorize(fn (Dispatch $record) => auth()->user()?->can('approve', $record) ?? false)
                ->action(function (Action $action, Dispatch $dispatch): void {
                    try {
                        app(ApproveDispatchAction::class)->handle(dispatch: $dispatch, approvedBy: auth()->id(), checkForConflicts: false);
                    } catch (DomainException $exception) {
                        Notification::make()
                            ->title('Dispatch approval failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                })
                ->successRedirectUrl(fn (): string => DispatchResource::getUrl('index'))
                ->requiresConfirmation(),

            Action::make('void')
                ->color('danger')
                ->label('Void')
                ->icon('heroicon-o-no-symbol')
                ->authorize(fn (Dispatch $record) => auth()->user()?->can('void', $record) ?? false)
                ->schema([
                    Textarea::make('reason')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(fn (Dispatch $dispatch, array $data) => app(VoidDispatchAction::class)->handle($dispatch, new VoidDispatchData(
                    voidedBy: auth()->id(),
                    reason: (string) $data['reason'],
                )))
                ->requiresConfirmation(),

            ActionGroup::make([
                EditAction::make()
                    ->authorize(fn (Dispatch $record) => auth()->user()?->can('update', $record) ?? false),

                DeleteAction::make()
                    ->authorize(fn (Dispatch $record) => auth()->user()?->can('delete', $record) ?? false),
            ]),
        ];
    }
}
