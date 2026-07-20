<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchEntriesResources\Actions;

use DomainException;
use Filament\Actions\BulkAction;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Actions\ReceiveContainerReturnAction;
use Storix\Data\ReceiveContainerReturnData;
use Storix\Filament\Resources\DispatchEntriesResources\Schemas\ReceiveContainerReturnForm;
use Storix\Models\DispatchEntry;
use Throwable;

final class ReceiveSelectedContainersBulkAction extends BulkAction
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (): string => 'Return Selected '.$this->containerLabel())
            ->modalHeading(fn (): string => 'Receive '.$this->containerLabel())
            ->modalSubmitActionLabel(fn (): string => 'Receive '.$this->containerLabel())
            ->icon('heroicon-o-archive-box-arrow-down')
            ->schema(fn (): array => ReceiveContainerReturnForm::components())
            ->authorize(fn (): bool => auth()->user()?->can('receive', new DispatchEntry) ?? false)
            ->authorizeIndividualRecords('receive')
            ->action(function (self $action, array $data): void {
                $action->receiveSelectedRecords(
                    $action->getIndividuallyAuthorizedSelectedRecords(),
                    $data,
                );
            })
            ->successNotificationTitle(fn (): string => $this->containerLabel().' received')
            ->failureNotificationTitle(function (int $successCount, int $totalCount): string {
                if ($successCount > 0) {
                    return "{$successCount} of {$totalCount} ".$this->containerLabel(lowercase: true).' received';
                }

                return 'No '.$this->containerLabel(lowercase: true).' received';
            })
            ->missingBulkAuthorizationFailureNotificationMessage(
                fn (int $failureCount): string => "{$failureCount} selected ".$this->containerLabel(lowercase: true).' were not authorized for receipt.',
            )
            ->deselectRecordsAfterCompletion();
    }

    #[Override]
    public static function getDefaultName(): string
    {
        return 'receiveSelectedContainers';
    }

    private function containerLabel(bool $lowercase = false): string
    {
        $label = str(Config::string('storix.labels.container'))
            ->plural()
            ->headline()
            ->toString();

        return $lowercase ? mb_strtolower($label) : $label;
    }

    /**
     * @param  iterable<array-key, mixed>  $records
     * @param  array<string, mixed>  $data
     */
    private function receiveSelectedRecords(iterable $records, array $data): void
    {
        foreach ($records as $record) {
            if (! $record instanceof DispatchEntry) {
                $this->reportBulkProcessingFailure(
                    key: 'invalid-record',
                    message: fn (int $failureCount): string => "{$failureCount} selected records were not valid ".$this->containerLabel(lowercase: true).'.',
                );

                continue;
            }

            try {
                app(ReceiveContainerReturnAction::class)->handle(
                    $record,
                    new ReceiveContainerReturnData(
                        returnDate: $data['return_date'],
                        condition: $data['return_condition'],
                        receivedBy: auth()->id(),
                        note: $data['return_note'] ?? null,
                    ),
                );
            } catch (Throwable $exception) {
                $this->reportReceiveFailure($exception);
            }
        }
    }

    private function reportReceiveFailure(Throwable $exception): void
    {
        $failure = $exception instanceof Halt && $exception->getPrevious() instanceof Throwable
            ? $exception->getPrevious()
            : $exception;
        $message = $failure->getMessage() ?: 'The return could not be recorded.';

        $this->reportBulkProcessingFailure(
            key: hash('sha256', $failure::class."\0{$message}"),
            message: fn (int $failureCount): string => "{$failureCount} selected ".$this->containerLabel(lowercase: true)." could not be received: {$message}",
        );

        if (! $failure instanceof DomainException) {
            report($failure);
        }
    }
}
