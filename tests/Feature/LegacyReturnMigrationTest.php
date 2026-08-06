<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Storix\Support\TableNames;

it('backfills dispatch customers and groups legacy receipts by customer and date', function (): void {
    Config::set('database.connections.legacy_upgrade', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    $originalConnection = Config::string('database.default');
    Config::set('database.default', 'legacy_upgrade');
    DB::purge('legacy_upgrade');

    try {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('delivery_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        foreach ([
            '2026_02_19_092100_create_containers_table.php',
            '2026_02_19_092150_create_dispatches_table.php',
            '2026_02_19_092200_create_dispatch_entries_table.php',
            '2026_07_20_000000_add_idempotency_key_to_dispatches_table.php',
            '2026_07_21_000000_add_approved_at_index_to_dispatches_table.php',
            '2026_07_30_000000_create_container_returns_table.php',
            '2026_07_30_000100_create_container_return_entries_table.php',
        ] as $migrationFile) {
            $migration = require __DIR__."/../../database/migrations/{$migrationFile}";
            $migration->up();
        }

        $timestamp = '2026-07-15 09:30:00';
        $userId = DB::table('users')->insertGetId([
            'name' => 'Legacy Receiver',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Legacy Customer',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $deliveryNoteId = DB::table('delivery_notes')->insertGetId([
            'customer_id' => $customerId,
            'name' => 'Legacy Delivery',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $containerId = DB::table(TableNames::containers())->insertGetId([
            'name' => 'Legacy Container',
            'serial' => 'LEGACY-001',
            'is_active' => true,
            'replacement_cost' => 100,
            'replacement_currency' => 'USD',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $secondContainerId = DB::table(TableNames::containers())->insertGetId([
            'name' => 'Second Legacy Container',
            'serial' => 'LEGACY-002',
            'is_active' => true,
            'replacement_cost' => 100,
            'replacement_currency' => 'USD',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $dispatchId = DB::table(TableNames::dispatches())->insertGetId([
            'code' => 'DSP-LEGACY',
            'quantity' => 2,
            'dispatched_by' => $userId,
            'delivery_note_id' => $deliveryNoteId,
            'dispatched_at' => '2026-07-10 08:00:00',
            'state' => 'approved',
            'approved_by' => $userId,
            'approved_at' => '2026-07-10 08:30:00',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $secondDispatchEntryId = DB::table(TableNames::dispatchEntries())->insertGetId([
            'container_id' => $secondContainerId,
            'dispatch_id' => $dispatchId,
            'received_by' => $userId,
            'return_date' => '2026-07-15',
            'return_condition' => 'good',
            'return_note' => 'Returned together',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $dispatchEntryId = DB::table(TableNames::dispatchEntries())->insertGetId([
            'container_id' => $containerId,
            'dispatch_id' => $dispatchId,
            'received_by' => $userId,
            'return_date' => '2026-07-15',
            'return_condition' => 'damaged',
            'return_note' => 'Legacy dent',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $migration = require __DIR__.'/../../database/migrations/2026_07_30_000200_refactor_dispatches_and_migrate_returns.php';
        $migration->up();

        $containerReturn = DB::table(TableNames::containerReturns())->sole();
        $returnEntries = DB::table(TableNames::containerReturnEntries())
            ->orderBy('dispatch_entry_id')
            ->get();
        $returnEntry = DB::table(TableNames::containerReturnEntries())
            ->where('dispatch_entry_id', $dispatchEntryId)
            ->sole();
        $secondReturnEntry = DB::table(TableNames::containerReturnEntries())
            ->where('dispatch_entry_id', $secondDispatchEntryId)
            ->sole();
        $dispatch = DB::table(TableNames::dispatches())->where('id', $dispatchId)->sole();

        expect($dispatch->customer_id)->toBe($customerId)
            ->and($containerReturn->customer_id)->toBe($customerId)
            ->and($containerReturn->user_id)->toBe($userId)
            ->and($containerReturn->approved_by)->toBe($userId)
            ->and($containerReturn->transaction_date)->toBe('2026-07-15')
            ->and($containerReturn->state)->toBe('approved')
            ->and($returnEntry->dispatch_entry_id)->toBe($dispatchEntryId)
            ->and($returnEntry->container_id)->toBe($containerId)
            ->and($returnEntry->return_condition)->toBe('damaged')
            ->and($returnEntry->note)->toBe('Legacy dent')
            ->and($returnEntries)->toHaveCount(2)
            ->and($secondReturnEntry->container_return_id)->toBe($containerReturn->id)
            ->and($secondReturnEntry->container_id)->toBe($secondContainerId)
            ->and($secondReturnEntry->return_condition)->toBe('good')
            ->and($secondReturnEntry->note)->toBe('Returned together')
            ->and(Schema::hasColumn(TableNames::dispatchEntries(), 'return_date'))->toBeFalse();
    } finally {
        DB::disconnect('legacy_upgrade');
        Config::set('database.default', $originalConnection);
    }
});
