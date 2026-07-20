<?php

declare(strict_types=1);

namespace Storix\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Storix\Actions\Concerns\NotifiesFilamentOfExceptions;
use Storix\Support\TableNames;
use Throwable;

final readonly class MarkDeliveryNoteAsDispatchedAction
{
    use NotifiesFilamentOfExceptions;

    /**
     * @throws Throwable
     */
    public function handle(int|string $deliveryNoteId, CarbonInterface $dispatchedAt): void
    {
        try {
            $deliveryNotesTable = TableNames::deliveryNotes();

            if (! Schema::hasTable($deliveryNotesTable) || ! Schema::hasColumn($deliveryNotesTable, 'dispatched_at')) {
                return;
            }

            DB::table($deliveryNotesTable)
                ->where('id', $deliveryNoteId)
                ->update(['dispatched_at' => $dispatchedAt]);
        } catch (Throwable $exception) {
            $this->handleException($exception);
        }
    }
}
