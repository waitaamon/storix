<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Override;
use Storix\Filament\Resources\DispatchResources\DispatchResource;
use Storix\Models\Dispatch;
use Storix\Models\States\DispatchApprovedState;
use Storix\Models\States\DispatchDraftState;

final class ViewDispatch extends ViewRecord
{
    protected static string $resource = DispatchResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->color('success')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->authorize(fn (Dispatch $record) => auth()->user()->can('approve', $record))
                ->action(fn (Dispatch $dispatch) => $dispatch->state->transitionTo(DispatchApprovedState::class))
                ->requiresConfirmation(),

            Action::make('draft')
                ->color('gray')
                ->label('Return to Draft')
                ->icon('heroicon-o-arrow-uturn-left')
                ->authorize(fn (Dispatch $record) => auth()->user()->can('draft', $record))
                ->action(fn (Dispatch $dispatch) => $dispatch->state->transitionTo(DispatchDraftState::class))
                ->requiresConfirmation(),

            ActionGroup::make([
                EditAction::make()
                    ->authorize(fn (Dispatch $record) => auth()->user()->can('update', $record)),

                DeleteAction::make()
                    ->authorize(fn (Dispatch $record) => auth()->user()->can('delete', $record)),
            ]),
        ];
    }
}
