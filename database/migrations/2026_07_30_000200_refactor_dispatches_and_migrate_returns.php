<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Storix\Support\TableNames;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(TableNames::dispatches(), function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('code');
            $table->foreignId('delivery_note_id')->nullable()->change();
        });

        $this->backfillDispatchCustomers();
        $this->migrateLegacyReturns();

        Schema::table(TableNames::dispatches(), function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable(false)->change();
        });

        Schema::table(TableNames::dispatchEntries(), function (Blueprint $table): void {
            $table->dropConstrainedForeignId('received_by');
            $table->dropIndex(['return_date']);
            $table->dropIndex(['return_condition']);
            $table->dropColumn(['return_date', 'return_condition', 'return_note']);
        });
    }

    private function backfillDispatchCustomers(): void
    {
        $dispatchTable = TableNames::dispatches();
        $deliveryNoteTable = TableNames::deliveryNotes();

        $unresolvedDispatchExists = DB::table("{$dispatchTable} as dispatches")
            ->leftJoin(
                "{$deliveryNoteTable} as delivery_notes",
                'delivery_notes.id',
                '=',
                'dispatches.delivery_note_id',
            )
            ->whereNull('delivery_notes.customer_id')
            ->exists();

        if ($unresolvedDispatchExists) {
            throw new RuntimeException(
                'Every legacy Storix dispatch must reference a delivery note with a customer before this migration can run.',
            );
        }

        DB::table("{$dispatchTable} as dispatches")
            ->join(
                "{$deliveryNoteTable} as delivery_notes",
                'delivery_notes.id',
                '=',
                'dispatches.delivery_note_id',
            )
            ->select(['dispatches.id', 'delivery_notes.customer_id'])
            ->orderBy('dispatches.id')
            ->chunkById(500, function ($dispatches) use ($dispatchTable): void {
                foreach ($dispatches as $dispatch) {
                    DB::table($dispatchTable)
                        ->where('id', $dispatch->id)
                        ->update(['customer_id' => $dispatch->customer_id]);
                }
            }, 'dispatches.id', 'id');
    }

    private function migrateLegacyReturns(): void
    {
        $dispatchEntryTable = TableNames::dispatchEntries();
        $dispatchTable = TableNames::dispatches();
        $containerReturnTable = TableNames::containerReturns();
        $containerReturnEntryTable = TableNames::containerReturnEntries();
        $containerReturnIds = [];

        DB::table("{$dispatchEntryTable} as entries")
            ->join("{$dispatchTable} as dispatches", 'dispatches.id', '=', 'entries.dispatch_id')
            ->whereNull('entries.deleted_at')
            ->where(function ($query): void {
                $query->whereNotNull('entries.return_date')
                    ->orWhereNotNull('entries.return_condition');
            })
            ->select([
                'entries.id',
                'entries.container_id',
                'entries.received_by',
                'entries.return_date',
                'entries.return_condition',
                'entries.return_note',
                'entries.created_at',
                'entries.updated_at',
                'dispatches.customer_id',
                'dispatches.dispatched_by',
            ])
            ->orderBy('entries.id')
            ->chunkById(500, function ($entries) use (
                $containerReturnTable,
                $containerReturnEntryTable,
                &$containerReturnIds,
            ): void {
                foreach ($entries as $entry) {
                    $transactionDate = Carbon::parse(
                        $entry->return_date ?? $entry->updated_at ?? now(),
                    )->toDateString();
                    $approvedAt = $entry->updated_at ?? Carbon::parse($transactionDate)->endOfDay();
                    $actorId = $entry->received_by ?? $entry->dispatched_by;
                    $timestamp = $entry->updated_at ?? now();
                    $returnGroup = "{$entry->customer_id}:{$transactionDate}";

                    if (! isset($containerReturnIds[$returnGroup])) {
                        $containerReturnId = DB::table($containerReturnTable)->insertGetId([
                            'code' => null,
                            'customer_id' => $entry->customer_id,
                            'user_id' => $actorId,
                            'approved_by' => $actorId,
                            'approved_at' => $approvedAt,
                            'note' => 'Migrated from legacy dispatch entries.',
                            'state' => 'approved',
                            'transaction_date' => $transactionDate,
                            'created_at' => $entry->created_at ?? $timestamp,
                            'updated_at' => $timestamp,
                            'deleted_at' => null,
                        ]);

                        DB::table($containerReturnTable)
                            ->where('id', $containerReturnId)
                            ->update([
                                'code' => 'CRN-'
                                    .Carbon::parse($transactionDate)->format('ymd')
                                    .str((string) $containerReturnId)->padLeft(4, '0'),
                            ]);

                        $containerReturnIds[$returnGroup] = $containerReturnId;
                    }

                    DB::table($containerReturnEntryTable)->insert([
                        'container_return_id' => $containerReturnIds[$returnGroup],
                        'container_id' => $entry->container_id,
                        'dispatch_entry_id' => $entry->id,
                        'return_condition' => $entry->return_condition ?? 'good',
                        'note' => $entry->return_note,
                        'cross_return' => false,
                        'created_at' => $entry->created_at ?? $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    if ($entry->return_condition === 'lost') {
                        DB::table(TableNames::containers())
                            ->where('id', $entry->container_id)
                            ->update(['is_active' => false]);
                    }
                }
            }, 'entries.id', 'id');
    }
};
