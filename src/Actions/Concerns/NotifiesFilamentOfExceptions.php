<?php

declare(strict_types=1);

namespace Storix\Actions\Concerns;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Throwable;

trait NotifiesFilamentOfExceptions
{
    private function handleException(Throwable $exception): never
    {
        if ($exception instanceof Halt || ! Filament::isServing()) {
            throw $exception;
        }

        Notification::make()
            ->title($exception->getMessage())
            ->danger()
            ->send();

        throw new Halt(previous: $exception)->rollBackDatabaseTransaction();
    }
}
