<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\Customer;

it('creates the optimized event-based container movements view', function (): void {
    expect(Schema::hasView(TableNames::containerMovements()))->toBeTrue()
        ->and(Schema::hasColumns(TableNames::containerMovements(), [
            'id',
            'container_id',
            'movement_date',
            'customer_id',
            'document_type',
            'document_id',
            'document_code',
            'cross_return',
        ]))->toBeTrue()
        ->and(Schema::hasColumn(TableNames::containerMovements(), 'status'))->toBeFalse()
        ->and(Schema::hasIndex(
            TableNames::dispatchEntries(),
            ['container_id', 'deleted_at'],
        ))->toBeTrue();
});

it('emits independent dispatch and return events without requiring reconciliation links', function (): void {
    $dispatchCustomer = Customer::query()->create(['name' => 'Event Dispatch Customer']);
    $returnCustomer = Customer::query()->create(['name' => 'Event Return Customer']);
    $container = Container::factory()->create();
    $dispatch = Dispatch::factory()->create([
        'customer_id' => $dispatchCustomer->id,
        'quantity' => 1,
        'state' => 'approved',
        'dispatched_at' => '2026-08-01 09:30:00',
        'approved_at' => '2026-08-01 10:00:00',
    ]);
    $dispatchEntry = DispatchEntry::factory()->create([
        'container_id' => $container->id,
        'dispatch_id' => $dispatch->id,
    ]);
    $containerReturn = ContainerReturn::factory()->approved()->create([
        'customer_id' => $returnCustomer->id,
        'transaction_date' => '2026-08-03',
    ]);
    $returnEntry = ContainerReturnEntry::factory()->crossReturn()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $container->id,
        'dispatch_entry_id' => null,
        'return_condition' => ReturnCondition::Good,
    ]);

    $movements = DB::table(TableNames::containerMovements())
        ->where('container_id', $container->id)
        ->orderBy('movement_date')
        ->get();
    $dispatchMovement = $movements->first();
    $returnMovement = $movements->last();

    expect($movements)->toHaveCount(2)
        ->and($dispatchMovement?->id)->toBe("dispatch:{$dispatchEntry->id}")
        ->and($dispatchMovement?->customer_id)->toBe($dispatchCustomer->id)
        ->and($dispatchMovement?->document_type)->toBe('dispatch')
        ->and($dispatchMovement?->document_id)->toBe($dispatch->id)
        ->and($dispatchMovement?->document_code)->toBe($dispatch->code)
        ->and($dispatchMovement?->cross_return)->toBeNull()
        ->and($returnMovement?->id)->toBe("return:{$returnEntry->id}")
        ->and($returnMovement?->customer_id)->toBe($returnCustomer->id)
        ->and($returnMovement?->document_type)->toBe('return')
        ->and($returnMovement?->document_id)->toBe($containerReturn->id)
        ->and($returnMovement?->document_code)->toBe($containerReturn->code)
        ->and(CarbonImmutable::parse($returnMovement?->movement_date)->toDateString())->toBe('2026-08-03')
        ->and($returnMovement?->cross_return)->toBe(1);
});

it('shows only posted return events and updates live when their state changes', function (): void {
    $container = Container::factory()->create();
    $containerReturn = ContainerReturn::factory()->submitted()->create();
    $returnEntry = ContainerReturnEntry::factory()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $container->id,
        'dispatch_entry_id' => null,
    ]);

    expect(DB::table(TableNames::containerMovements())->where('container_id', $container->id)->exists())
        ->toBeFalse();

    $containerReturn->forceFill([
        'state' => 'approved',
        'approved_at' => now(),
    ])->save();

    expect(DB::table(TableNames::containerMovements())
        ->where('id', "return:{$returnEntry->id}")
        ->value('document_type'))->toBe('return');

    $containerReturn->delete();

    expect(DB::table(TableNames::containerMovements())->where('container_id', $container->id)->exists())
        ->toBeFalse();
});

it('excludes draft voided and soft-deleted outbound events', function (): void {
    $draftEntry = DispatchEntry::factory()->create([
        'dispatch_id' => Dispatch::factory()->create(['state' => 'draft'])->id,
    ]);
    $voidedEntry = DispatchEntry::factory()->create([
        'dispatch_id' => Dispatch::factory()->create(['state' => 'voided'])->id,
    ]);
    $deletedDispatch = Dispatch::factory()->create([
        'state' => 'approved',
        'approved_at' => now(),
    ]);
    $deletedDispatchEntry = DispatchEntry::factory()->create([
        'dispatch_id' => $deletedDispatch->id,
    ]);
    $deletedDispatch->delete();
    $deletedEntry = DispatchEntry::factory()->create([
        'dispatch_id' => Dispatch::factory()->create([
            'state' => 'approved',
            'approved_at' => now(),
        ])->id,
    ]);
    $deletedEntry->delete();

    $movementIds = DB::table(TableNames::containerMovements())->pluck('id')->all();

    expect($movementIds)->not->toContain(
        "dispatch:{$draftEntry->id}",
        "dispatch:{$voidedEntry->id}",
        "dispatch:{$deletedDispatchEntry->id}",
        "dispatch:{$deletedEntry->id}",
    );
});
