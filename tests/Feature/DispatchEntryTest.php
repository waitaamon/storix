<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\DispatchEntriesResources\Pages\ListDispatchEntries;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

it('defines return_date as a nullable indexed date column', function (): void {
    $returnDateColumn = collect(DatabaseSchema::getColumns(TableNames::dispatchEntries()))
        ->firstWhere('name', 'return_date');

    expect($returnDateColumn)->not->toBeNull()
        ->and(DatabaseSchema::getColumnType(TableNames::dispatchEntries(), 'return_date'))->toBe('date')
        ->and($returnDateColumn['nullable'] ?? null)->toBeTrue()
        ->and(DatabaseSchema::hasIndex(TableNames::dispatchEntries(), ['return_date']))->toBeTrue();
});

it('creates a dispatch entry linking a container to a dispatch', function (): void {
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

    expect($entry->exists)->toBeTrue()
        ->and($entry->container->id)->toBe($container->id)
        ->and($entry->dispatch->id)->toBe($dispatch->id);
});

it('records return information on a dispatch entry', function (): void {
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $receiver = User::query()->create(['name' => 'Receiver', 'email' => 'receive@example.com']);
    $container = Container::factory()->create();
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Test delivery']);

    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $dispatcher->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
    ]);

    $entry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);

    $entry->update([
        'received_by' => $receiver->id,
        'return_date' => '2026-02-15 16:45:30',
        'return_condition' => ReturnCondition::Good,
        'return_note' => 'Returned in good condition',
    ]);

    $entry->refresh();

    expect($entry->return_date)->toBeInstanceOf(CarbonImmutable::class)
        ->and($entry->return_date?->toDateString())->toBe('2026-02-15')
        ->and($entry->return_date?->isStartOfDay())->toBeTrue()
        ->and($entry->return_condition)->toBe(ReturnCondition::Good)
        ->and($entry->return_note)->toBe('Returned in good condition')
        ->and($entry->received_by)->toBe($receiver->id);
});

it('defaults the receive action to today and good condition', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);

    $user = User::query()->create(['name' => 'Receiver', 'email' => 'receive-defaults@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Default return delivery']);
    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
    ]);
    $entry = DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => Container::factory()->create()->id,
    ]);

    actingAs($user);
    Filament::setCurrentPanel('test');

    $page = app(ListDispatchEntries::class);
    $page->mount();
    $page->mountInteractsWithTable();
    $page->bootedInteractsWithTable();
    $page->mountTableAction('edit', (string) $entry->getKey());

    $schema = $page->getMountedTableActionForm();

    if (! $schema instanceof FilamentSchema) {
        throw new LogicException('The receive container schema was not mounted.');
    }

    $state = $schema->getState();

    expect(CarbonImmutable::parse($state['return_date'])->isToday())->toBeTrue()
        ->and($state['return_condition'])->toBe(ReturnCondition::Good);
});

it('deletes a dispatch entry', function (): void {
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

    $entryId = $entry->id;
    $entry->delete();

    expect(DispatchEntry::query()->find($entryId))->toBeNull();
});

it('associates multiple containers with a single dispatch', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
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
        ->and($container = $containers->first())
        ->and($container->entries)->toHaveCount(1);
});

it('scopes availableForDispatch to active containers without unreturned entries', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Test']);

    $available = Container::factory()->create(['is_active' => true]);
    $inactive = Container::factory()->create(['is_active' => false]);
    $dispatched = Container::factory()->create(['is_active' => true]);
    $returned = Container::factory()->create(['is_active' => true]);

    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 2,
        'state' => 'approved',
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

it('excludes containers reserved on draft dispatches from availableForDispatch', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Test']);

    $container = Container::factory()->create(['is_active' => true]);

    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
        // state defaults to draft
    ]);

    // Draft entries reserve containers until they are returned or the dispatch is voided.
    DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);

    $result = Container::query()->availableForDispatch()->pluck('id');

    expect($result)->not->toContain($container->id);
});
