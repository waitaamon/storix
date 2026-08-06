<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Storix\Actions\ApproveDispatchAction;
use Storix\Actions\AttachContainersToDispatchAction;
use Storix\Actions\CreateDispatchAction;
use Storix\Actions\MarkDeliveryNoteAsDispatchedAction;
use Storix\Actions\VoidDispatchAction;
use Storix\Data\CreateDispatchData;
use Storix\Data\VoidDispatchData;
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
        customerId: $deliveryNote->customer_id,
        dispatchedAt: '2026-02-12',
        containerIds: [$container->id],
    ));

    expect($dispatch->quantity)->toBe(1)
        ->and($dispatch->entries)->toHaveCount(1)
        ->and(Container::query()->availableForDispatch()->pluck('id'))->not->toContain($container->id);
});

it('can attach an unavailable container when the availability check is disabled', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'unchecked-dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Unchecked attachment delivery']);
    $container = Container::factory()->create();

    $reservedDispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        customerId: $deliveryNote->customer_id,
        containerIds: [$container->id],
    ));
    $otherDispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        customerId: $deliveryNote->customer_id,
    ));

    expect(fn () => app(AttachContainersToDispatchAction::class)->handle(
        $otherDispatch,
        [$container->id],
    ))->toThrow(
        DomainException::class,
        "Container [{$container->serial}] is already reserved or dispatched.",
    );

    app(AttachContainersToDispatchAction::class)->handle(
        $otherDispatch,
        [$container->id],
        checkAvailability: false,
    );

    expect($reservedDispatch->entries()->where('container_id', $container->id)->exists())->toBeTrue()
        ->and($otherDispatch->entries()->where('container_id', $container->id)->exists())->toBeTrue();
});

it('persists the declared quantity as an integer', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'quantity@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Quantity delivery']);

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 12,
        customerId: $deliveryNote->customer_id,
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
        customerId: $deliveryNote->customer_id,
    )))->toThrow(DomainException::class, 'The dispatch quantity must be at least 1.');

    expect(Dispatch::query()->count())->toBe(0);
})->with([0, -1]);

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
        customerId: $deliveryNote->customer_id,
        dispatchedAt: '2026-02-12',
        containerIds: $containers->modelKeys(),
    ));

    $approved = app(ApproveDispatchAction::class)->handle($dispatch, $user->id);

    expect((string) $approved->state)->toBe('approved')
        ->and($approved->approved_by)->toBe($user->id)
        ->and($approved->approved_at)->not->toBeNull();
});

it('can approve conflicting dispatch entries when the conflict check is disabled', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'unchecked-approval@example.com']);
    $firstDeliveryNote = DeliveryNote::query()->create(['name' => 'First conflicting approval']);
    $secondDeliveryNote = DeliveryNote::query()->create(['name' => 'Second conflicting approval']);
    $container = Container::factory()->create();

    $firstDispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $firstDeliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        customerId: $firstDeliveryNote->customer_id,
        containerIds: [$container->id],
    ));
    $secondDispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $secondDeliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        customerId: $secondDeliveryNote->customer_id,
    ));
    app(AttachContainersToDispatchAction::class)->handle(
        $secondDispatch,
        [$container->id],
        checkAvailability: false,
    );

    expect(fn () => app(ApproveDispatchAction::class)->handle($firstDispatch, $user->id))
        ->toThrow(
            DomainException::class,
            "Container [{$container->serial}] is already reserved or dispatched.",
        );

    $approved = app(ApproveDispatchAction::class)->handle(
        $firstDispatch,
        $user->id,
        checkForConflicts: false,
    );

    expect((string) $approved->state)->toBe('approved');
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
        customerId: $deliveryNote->customer_id,
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
        customerId: $deliveryNote->customer_id,
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
        customerId: $deliveryNote->customer_id,
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
        customerId: $deliveryNote->customer_id,
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
        customerId: $deliveryNote->customer_id,
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
        customerId: $deliveryNote->customer_id,
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

it('voids draft dispatches and releases reserved containers', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Void delivery']);
    $container = Container::factory()->create();

    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        customerId: $deliveryNote->customer_id,
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

it('shows action exceptions as danger notifications while Filament is serving', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'notifications@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Notification delivery']);
    $container = Container::factory()->create();

    $emptyDispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
    ]);

    $draftDispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        customerId: $deliveryNote->customer_id,
        containerIds: [$container->id],
    ));
    $approvedDispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $user->id,
        quantity: 1,
        customerId: $deliveryNote->customer_id,
        containerIds: [Container::factory()->create()->id],
    ));
    app(ApproveDispatchAction::class)->handle($approvedDispatch, $user->id);

    $assertDangerNotification = function (Closure $callback, ?string $message = null): void {
        session()->forget('filament.notifications');

        try {
            $callback();
            throw new LogicException('The action did not halt after throwing an exception.');
        } catch (Halt $exception) {
            $originalException = $exception->getPrevious();
            $notifications = session('filament.notifications', []);

            expect($exception->shouldRollbackDatabaseTransaction())->toBeTrue()
                ->and($originalException)->not->toBeNull()
                ->and($notifications)->toHaveCount(1)
                ->and($notifications[0]['status'])->toBe('danger')
                ->and($notifications[0]['title'])->toBe($originalException?->getMessage());

            if ($message !== null) {
                expect($originalException?->getMessage())->toBe($message);
            }
        }
    };

    Filament::setServingStatus();

    try {
        $assertDangerNotification(
            fn () => app(CreateDispatchAction::class)->handle(new CreateDispatchData(
                deliveryNoteId: $deliveryNote->id,
                dispatchedBy: $user->id,
                quantity: 1,
                customerId: $deliveryNote->customer_id,
                idempotencyKey: str_repeat('x', 256),
            )),
            'The dispatch idempotency key may not exceed 255 characters.',
        );

        $assertDangerNotification(
            fn () => app(ApproveDispatchAction::class)->handle($emptyDispatch, $user->id),
            'A dispatch cannot be approved without containers.',
        );

        $assertDangerNotification(
            fn () => app(AttachContainersToDispatchAction::class)->handle(
                $approvedDispatch,
                [Container::factory()->create()->id],
            ),
            'Containers can only be attached to draft dispatches.',
        );

        $assertDangerNotification(
            fn () => app(VoidDispatchAction::class)->handle($draftDispatch, new VoidDispatchData(
                voidedBy: $user->id,
                reason: '',
            )),
            'A void reason is required.',
        );

        Config::set('storix.tables.delivery_notes', []);

        $assertDangerNotification(fn () => app(MarkDeliveryNoteAsDispatchedAction::class)->handle(
            $deliveryNote->id,
            now(),
        ));
    } finally {
        Filament::setServingStatus(false);
        Config::set('storix.tables.delivery_notes', 'delivery_notes');
    }
});
