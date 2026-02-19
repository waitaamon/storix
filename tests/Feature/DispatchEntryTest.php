<?php

declare(strict_types=1);

use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\User;

it('creates a dispatch entry linking a container to a dispatch', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $container = Container::factory()->create();

    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => 'Test delivery',
    ]);

    $entry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);

    expect($entry->exists)->toBeTrue()
        ->and($entry->container->id)->toBe($container->id)
        ->and($entry->dispatch->id)->toBe($dispatch->id);
});

it('records return information on a dispatch entry', function (): void {
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $receiver = User::query()->create(['name' => 'Receiver', 'email' => 'receive@example.com']);
    $container = Container::factory()->create();

    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $dispatcher->id,
        'delivery_note_id' => 'Test delivery',
    ]);

    $entry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);

    $entry->update([
        'received_by' => $receiver->id,
        'return_date' => '2026-02-15',
        'return_condition' => ReturnCondition::Good,
        'return_note' => 'Returned in good condition',
    ]);

    $entry->refresh();

    expect($entry->return_condition)->toBe(ReturnCondition::Good)
        ->and($entry->return_note)->toBe('Returned in good condition')
        ->and($entry->receivedBy->id)->toBe($receiver->id);
});

it('soft deletes and restores a dispatch entry', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $container = Container::factory()->create();

    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => 'Test delivery',
    ]);

    $entry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);

    $entry->delete();

    expect(DispatchEntry::query()->find($entry->id))->toBeNull()
        ->and(DispatchEntry::withTrashed()->find($entry->id))->not->toBeNull();

    $entry->restore();

    expect(DispatchEntry::query()->find($entry->id))->not->toBeNull();
});

it('associates multiple containers with a single dispatch', function (): void {

    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $containers = Container::factory()->count(3)->create();

    $dispatch = Dispatch::query()->create([

        'dispatched_by' => $user->id,
        'delivery_note_id' => 'Bulk delivery',
    ]);

    foreach ($containers as $container) {
        DispatchEntry::query()->create([
            'dispatch_id' => $dispatch->id,
            'container_id' => $container->id,
        ]);
    }

    expect($dispatch->entries)->toHaveCount(3)
        ->and($container = $containers->first())
        ->and($container->entries)->toHaveCount(1);
});

it('scopes availableForDispatch to active containers without unreturned entries', function (): void {

    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);

    $available = Container::factory()->create(['is_active' => true]);
    $inactive = Container::factory()->create(['is_active' => false]);
    $dispatched = Container::factory()->create(['is_active' => true]);
    $returned = Container::factory()->create(['is_active' => true]);

    $dispatch = Dispatch::query()->create([

        'dispatched_by' => $user->id,
        'delivery_note_id' => 'Test',
    ]);

    // Container dispatched but not returned — should be excluded
    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $dispatched->id,
    ]);

    // Container dispatched and returned — should be available
    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $returned->id,
        'return_date' => '2026-02-10',
        'return_condition' => ReturnCondition::Good,
    ]);

    $result = Container::query()->availableForDispatch()->pluck('id');

    expect($result)->toContain($available->id)
        ->and($result)->toContain($returned->id)
        ->and($result)->not->toContain($inactive->id)
        ->and($result)->not->toContain($dispatched->id);
});

it('includes containers with soft-deleted unreturned entries in availableForDispatch', function (): void {

    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);

    $container = Container::factory()->create(['is_active' => true]);

    $dispatch = Dispatch::query()->create([

        'dispatched_by' => $user->id,
        'delivery_note_id' => 'Test',
    ]);

    // Entry is unreturned but soft-deleted — container should be available
    $entry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);
    $entry->delete();

    $result = Container::query()->availableForDispatch()->pluck('id');

    expect($result)->toContain($container->id);
});
