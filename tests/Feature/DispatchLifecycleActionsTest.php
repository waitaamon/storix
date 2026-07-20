<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Storix\Actions\ApproveDispatchAction;
use Storix\Actions\CreateDispatchAction;
use Storix\Actions\ReceiveContainerReturnAction;
use Storix\Actions\VoidDispatchAction;
use Storix\Data\CreateDispatchData;
use Storix\Data\ReceiveContainerReturnData;
use Storix\Data\VoidDispatchData;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

it('creates a draft dispatch and reserves selected containers', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Lifecycle delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        dispatchedAt: '2026-02-12',
        containerIds: [$container->id],
    ));

    expect($dispatch->quantity)->toBe(1)
        ->and($dispatch->entries)->toHaveCount(1)
        ->and(Container::query()->availableForDispatch()->pluck('id'))->not->toContain($container->id);
});

it('persists the declared quantity as an integer', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'quantity@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Quantity delivery']);

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 12,
    ));

    expect($dispatch->quantity)->toBe(12)
        ->and(Dispatch::query()->findOrFail($dispatch->id)->quantity)->toBe(12);
});

it('rejects a non-positive dispatch quantity without persisting a dispatch', function (int $quantity): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => "quantity-{$quantity}@example.com"]);
    $deliveryNote = DeliveryNote::query()->create(['name' => "Invalid quantity {$quantity}"]);

    expect(fn () => app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: $quantity,
    )))->toThrow(DomainException::class, 'The dispatch quantity must be at least 1.');

    expect(Dispatch::query()->count())->toBe(0);
})->with([0, -1]);

it('enforces positive quantities at the database boundary', function (): void {
    $user = User::query()->create(['name' => 'Database Dispatcher', 'email' => 'database-quantity@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Database quantity delivery']);

    expect(fn () => DB::table(TableNames::dispatches())->insert([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(Dispatch::query()->count())->toBe(0);
});

it('does not approve a dispatch without containers', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Empty dispatch']);

    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
    ]);

    app(ApproveDispatchAction::class)->handle($dispatch, $user->id);
})->throws(DomainException::class, 'A dispatch cannot be approved without containers.');

it('approves a reserved dispatch with audit fields', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Approval delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        dispatchedAt: '2026-02-12',
        containerIds: [$container->id],
    ));

    $approved = app(ApproveDispatchAction::class)->handle($dispatch, $user->id);

    expect((string) $approved->state)->toBe('approved')
        ->and($approved->approved_by)->toBe($user->id)
        ->and($approved->approved_at)->not->toBeNull();
});

it('receives a returned container through the lifecycle action', function (): void {
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $receiver = User::query()->create(['name' => 'Receiver', 'email' => 'receive@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Return delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $dispatcher->id,
        quantity: 1,
        dispatchedAt: '2026-02-12',
        containerIds: [$container->id],
    ));
    app(ApproveDispatchAction::class)->handle($dispatch, $dispatcher->id);

    $entry = DispatchEntry::query()->where('container_id', $container->id)->firstOrFail();

    $received = app(ReceiveContainerReturnAction::class)->handle($entry, new ReceiveContainerReturnData(
        returnDate: '2026-02-14',
        condition: ReturnCondition::Good,
        receivedBy: $receiver->id,
        note: 'Returned clean',
    ));

    expect($received->received_by)->toBe($receiver->id)
        ->and($received->return_condition)->toBe(ReturnCondition::Good)
        ->and(Container::query()->availableForDispatch()->pluck('id'))->toContain($container->id);
});

it('marks lost containers inactive when loss is recorded', function (): void {
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Lost delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $dispatcher->id,
        quantity: 1,
        dispatchedAt: '2026-02-12',
        containerIds: [$container->id],
    ));
    app(ApproveDispatchAction::class)->handle($dispatch, $dispatcher->id);

    $entry = DispatchEntry::query()->where('container_id', $container->id)->firstOrFail();

    app(ReceiveContainerReturnAction::class)->handle($entry, new ReceiveContainerReturnData(
        returnDate: '2026-02-18',
        condition: ReturnCondition::Lost,
        receivedBy: $dispatcher->id,
    ));

    expect($container->refresh()->is_active)->toBeFalse();
});

it('voids draft dispatches and releases reserved containers', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Void delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        dispatchedAt: '2026-02-12',
        containerIds: [$container->id],
    ));

    $voided = app(VoidDispatchAction::class)->handle($dispatch, new VoidDispatchData(
        voidedBy: $user->id,
        reason: 'Entered in error',
    ));

    expect((string) $voided->state)->toBe('voided')
        ->and($voided->void_reason)->toBe('Entered in error')
        ->and(DispatchEntry::withTrashed()->where('dispatch_id', $dispatch->id)->first()?->trashed())->toBeTrue()
        ->and(Container::query()->availableForDispatch()->pluck('id'))->toContain($container->id);
});
