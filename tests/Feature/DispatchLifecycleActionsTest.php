<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Storix\Actions\ApproveDispatchAction;
use Storix\Actions\CreateDispatchAction;
use Storix\Actions\ReceiveContainerReturnAction;
use Storix\Actions\VoidDispatchAction;
use Storix\Data\CreateDispatchData;
use Storix\Data\ReceiveContainerReturnData;
use Storix\Data\VoidDispatchData;
use Storix\Enums\ReturnCondition;
use Storix\Events\ContainerDispatched;
use Storix\Events\DispatchApproved;
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

    expect(fn () => app(ApproveDispatchAction::class)->handle($dispatch, $user->id))
        ->toThrow(DomainException::class, 'A dispatch cannot be approved without containers.');

    expect(DB::table(TableNames::deliveryNotes())
        ->where('id', $deliveryNote->id)
        ->value('dispatched_at'))->toBeNull();
});

it('approves a reserved dispatch with audit fields', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Approval delivery']);
    $containers = Container::factory()->count(2)->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 2,
        dispatchedAt: '2026-02-12',
        containerIds: $containers->modelKeys(),
    ));

    $approved = app(ApproveDispatchAction::class)->handle($dispatch, $user->id);

    expect((string) $approved->state)->toBe('approved')
        ->and($approved->approved_by)->toBe($user->id)
        ->and($approved->approved_at)->not->toBeNull();
});

it('marks only the approved dispatch delivery note as dispatched at the approval time', function (): void {
    $user = User::query()->create(['name' => 'Timestamp Dispatcher', 'email' => 'timestamp@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Timestamp delivery']);
    $unrelatedDeliveryNote = DeliveryNote::query()->create(['name' => 'Unrelated delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        containerIds: [$container->id],
    ));

    $approvalTime = Carbon::parse('2026-07-20 09:30:00');
    Carbon::setTestNow($approvalTime);

    try {
        $approved = app(ApproveDispatchAction::class)->handle($dispatch, $user->id);
        $deliveryNoteDispatchedAt = DB::table(TableNames::deliveryNotes())
            ->where('id', $deliveryNote->id)
            ->value('dispatched_at');

        expect($deliveryNoteDispatchedAt)->not->toBeNull()
            ->and(Carbon::parse((string) $deliveryNoteDispatchedAt)->equalTo($approvalTime))->toBeTrue()
            ->and($approved->approved_at?->equalTo($approvalTime))->toBeTrue()
            ->and(DB::table(TableNames::deliveryNotes())
                ->where('id', $unrelatedDeliveryNote->id)
                ->value('dispatched_at'))->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('updates the delivery note table configured by the host application', function (): void {
    $user = User::query()->create(['name' => 'Configured Dispatcher', 'email' => 'configured@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Configured delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        containerIds: [$container->id],
    ));

    Schema::create('host_delivery_notes', function (Blueprint $table): void {
        $table->id();
        $table->timestampTz('dispatched_at')->nullable();
    });
    DB::table('host_delivery_notes')->insert(['id' => $deliveryNote->id]);
    Config::set('storix.tables.delivery_notes', 'host_delivery_notes');

    $approvalTime = Carbon::parse('2026-07-20 10:45:00');
    Carbon::setTestNow($approvalTime);

    try {
        app(ApproveDispatchAction::class)->handle($dispatch, $user->id);
        $dispatchedAt = DB::table('host_delivery_notes')
            ->where('id', $deliveryNote->id)
            ->value('dispatched_at');

        expect($dispatchedAt)->not->toBeNull()
            ->and(Carbon::parse((string) $dispatchedAt)->equalTo($approvalTime))->toBeTrue()
            ->and(DB::table('delivery_notes')
                ->where('id', $deliveryNote->id)
                ->value('dispatched_at'))->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('approves a dispatch when the configured delivery note table does not exist', function (): void {
    $user = User::query()->create(['name' => 'Missing Table Dispatcher', 'email' => 'missing-table@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Missing table delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        containerIds: [$container->id],
    ));

    Config::set('storix.tables.delivery_notes', 'missing_delivery_notes');

    $approved = app(ApproveDispatchAction::class)->handle($dispatch, $user->id);

    expect((string) $approved->state)->toBe('approved');
});

it('approves a dispatch when the configured delivery note table has no dispatched timestamp', function (): void {
    $user = User::query()->create(['name' => 'Legacy Dispatcher', 'email' => 'legacy-table@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Legacy delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        containerIds: [$container->id],
    ));

    Schema::create('legacy_delivery_notes', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    DB::table('legacy_delivery_notes')->insert([
        'id' => $deliveryNote->id,
        'name' => 'Legacy delivery',
    ]);
    Config::set('storix.tables.delivery_notes', 'legacy_delivery_notes');

    $approved = app(ApproveDispatchAction::class)->handle($dispatch, $user->id);

    expect((string) $approved->state)->toBe('approved');
});

it('rejects approval when dispatch entry count does not match quantity', function (int $quantity, int $containerCount): void {
    Event::fake([DispatchApproved::class, ContainerDispatched::class]);

    $user = User::query()->create([
        'name' => 'Mismatch Dispatcher',
        'email' => "mismatch-{$quantity}-{$containerCount}@example.com",
    ]);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Mismatch delivery']);
    $containers = Container::factory()->count($containerCount)->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: $quantity,
        containerIds: $containers->modelKeys(),
    ));

    expect(fn () => app(ApproveDispatchAction::class)->handle($dispatch, $user->id))
        ->toThrow(
            DomainException::class,
            "The dispatch quantity [{$quantity}] must match the attached container count [{$containerCount}].",
        );

    $dispatch->refresh();

    expect((string) $dispatch->state)->toBe('draft')
        ->and($dispatch->approved_by)->toBeNull()
        ->and($dispatch->approved_at)->toBeNull();

    Event::assertNotDispatched(DispatchApproved::class);
    Event::assertNotDispatched(ContainerDispatched::class);
})->with([
    'under-allocated' => [2, 1],
    'over-allocated' => [1, 2],
]);

it('excludes soft-deleted dispatch entries from the approval quantity count', function (): void {
    $user = User::query()->create(['name' => 'Deleted Entry Dispatcher', 'email' => 'deleted-entry@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Deleted entry delivery']);
    $containers = Container::factory()->count(2)->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 2,
        containerIds: $containers->modelKeys(),
    ));

    $dispatch->entries()->firstOrFail()->delete();

    expect(fn () => app(ApproveDispatchAction::class)->handle($dispatch, $user->id))
        ->toThrow(
            DomainException::class,
            'The dispatch quantity [2] must match the attached container count [1].',
        );

    expect((string) $dispatch->refresh()->state)->toBe('draft')
        ->and($dispatch->approved_by)->toBeNull()
        ->and($dispatch->approved_at)->toBeNull()
        ->and($dispatch->entries()->count())->toBe(1)
        ->and(DispatchEntry::withTrashed()->where('dispatch_id', $dispatch->id)->count())->toBe(2);
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
