<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

it('keeps dispatch entries focused on dispatch movement data', function (): void {
    expect(Schema::hasColumns(TableNames::dispatchEntries(), [
        'id',
        'container_id',
        'dispatch_id',
        'metadata',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns(TableNames::dispatchEntries(), [
            'received_by',
            'return_date',
            'return_condition',
            'return_note',
        ]))->toBeFalse();
});

it('creates and deletes a dispatch entry linking a container to a dispatch', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $container = Container::factory()->create();
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Test delivery']);
    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
    ]);
    $entry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);

    expect($entry->container->is($container))->toBeTrue()
        ->and($entry->dispatch->is($dispatch))->toBeTrue();

    $entry->delete();

    expect(DispatchEntry::query()->find($entry->id))->toBeNull()
        ->and(DispatchEntry::withTrashed()->find($entry->id))->not->toBeNull();
});

it('associates multiple containers with one dispatch', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'bulk-dispatch@example.com']);
    $containers = Container::factory()->count(3)->create();
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Bulk delivery']);
    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 3,
    ]);

    foreach ($containers as $container) {
        DispatchEntry::query()->create([
            'dispatch_id' => $dispatch->id,
            'container_id' => $container->id,
        ]);
    }

    expect($dispatch->entries)->toHaveCount(3)
        ->and($dispatch->containers)->toHaveCount(3)
        ->and($containers->first()?->entries)->toHaveCount(1);
});

it('derives availability from approved return reconciliation', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'availability@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Availability delivery']);
    $available = Container::factory()->create(['is_active' => true]);
    $inactive = Container::factory()->create(['is_active' => false]);
    $dispatched = Container::factory()->create(['is_active' => true]);
    $returned = Container::factory()->create(['is_active' => true]);
    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 2,
        'state' => 'approved',
        'approved_at' => now(),
    ]);
    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $dispatched->id,
    ]);
    $returnedDispatchEntry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $returned->id,
    ]);
    $containerReturn = ContainerReturn::factory()->approved()->create();
    $returnEntry = ContainerReturnEntry::query()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $returned->id,
        'return_condition' => ReturnCondition::Good,
    ]);
    $returnEntry->forceFill(['dispatch_entry_id' => $returnedDispatchEntry->id])->save();

    $result = Container::query()->availableForDispatch()->pluck('id');

    expect($result)->toContain($available->id, $returned->id)
        ->and($result)->not->toContain($inactive->id, $dispatched->id);
});

it('excludes containers reserved on draft dispatches from availability', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'reservation@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Reservation delivery']);
    $container = Container::factory()->create(['is_active' => true]);
    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
    ]);
    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);

    expect(Container::query()->availableForDispatch()->pluck('id'))
        ->not->toContain($container->id);
});
