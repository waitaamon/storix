<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Storix\Actions\AddContainerReturnEntryAction;
use Storix\Actions\ApproveContainerReturnAction;
use Storix\Actions\ApproveDispatchAction;
use Storix\Actions\CreateContainerReturnAction;
use Storix\Actions\CreateDispatchAction;
use Storix\Actions\ReturnContainerReturnToDraftAction;
use Storix\Actions\SubmitContainerReturnAction;
use Storix\Actions\VoidDispatchAction;
use Storix\Data\AddContainerReturnEntryData;
use Storix\Data\CreateContainerReturnData;
use Storix\Data\CreateDispatchData;
use Storix\Data\VoidDispatchData;
use Storix\Enums\ReturnCondition;
use Storix\Events\ContainerDamaged;
use Storix\Events\ContainerLost;
use Storix\Events\ContainerReturnApproved;
use Storix\Events\ContainerReturned;
use Storix\Events\ContainerReturnSubmitted;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\CustomerContainerBalanceQuery;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

function createApprovedDispatchForReturn(
    Customer $customer,
    User $dispatcher,
    Container $container,
    string $date = '2026-07-01',
): Dispatch {
    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: null,
        dispatchedBy: $dispatcher->id,
        quantity: 1,
        customerId: $customer->id,
        dispatchedAt: $date,
        containerIds: [$container->id],
    ));

    return app(ApproveDispatchAction::class)->handle($dispatch, $dispatcher->id);
}

function createSubmittedReturn(
    Customer $customer,
    User $preparer,
    Container $container,
    ReturnCondition $condition = ReturnCondition::Good,
    string $date = '2026-07-02',
): ContainerReturn {
    $containerReturn = app(CreateContainerReturnAction::class)->handle(
        new CreateContainerReturnData(
            customerId: $customer->id,
            userId: $preparer->id,
            transactionDate: $date,
        ),
    );
    app(AddContainerReturnEntryAction::class)->handle(
        $containerReturn,
        new AddContainerReturnEntryData(
            containerId: $container->id,
            condition: $condition,
        ),
    );

    return app(SubmitContainerReturnAction::class)->handle($containerReturn);
}

function soleReturnEntry(ContainerReturn $containerReturn): ContainerReturnEntry
{
    $entry = $containerReturn->entries()->sole();

    if (! $entry instanceof ContainerReturnEntry) {
        throw new LogicException('The configured container return entry model is invalid.');
    }

    return $entry;
}

function soleDispatchEntry(Dispatch $dispatch): DispatchEntry
{
    $entry = $dispatch->entries()->sole();

    if (! $entry instanceof DispatchEntry) {
        throw new LogicException('The configured dispatch entry model is invalid.');
    }

    return $entry;
}

it('makes dispatch customer required, delivery note optional, and removes legacy return columns', function (): void {
    $dispatchColumns = collect(Schema::getColumns(TableNames::dispatches()));
    $customerColumn = $dispatchColumns->firstWhere('name', 'customer_id');
    $deliveryNoteColumn = $dispatchColumns->firstWhere('name', 'delivery_note_id');

    expect($customerColumn['nullable'] ?? null)->toBeFalse()
        ->and($deliveryNoteColumn['nullable'] ?? null)->toBeTrue()
        ->and(Schema::hasColumns(TableNames::dispatchEntries(), [
            'received_by',
            'return_date',
            'return_condition',
            'return_note',
        ]))->toBeFalse();
});

it('creates and approves a customer dispatch without a delivery note', function (): void {
    $customer = Customer::query()->create(['name' => 'Direct Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'direct-dispatch@example.com']);
    $container = Container::factory()->create();

    $dispatch = createApprovedDispatchForReturn($customer, $dispatcher, $container);

    expect($dispatch->customer->is($customer))->toBeTrue()
        ->and($dispatch->delivery_note_id)->toBeNull()
        ->and($dispatch->deliveryNote)->toBeNull()
        ->and((string) $dispatch->state)->toBe('approved');
});

it('rejects a delivery note belonging to another customer', function (): void {
    $customer = Customer::query()->create(['name' => 'Dispatch Customer']);
    $otherCustomer = Customer::query()->create(['name' => 'Delivery Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'mismatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create([
        'name' => 'Mismatched delivery',
        'customer_id' => $otherCustomer->id,
    ]);

    expect(fn () => app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $dispatcher->id,
        quantity: 1,
        customerId: $customer->id,
    )))->toThrow(DomainException::class, 'The selected delivery note does not belong to the dispatch customer.');

    expect(Dispatch::query()->doesntExist())->toBeTrue();
});

it('identifies the delivery note and customer when the delivery note has no customer', function (): void {
    Schema::table('delivery_notes', function (Blueprint $table): void {
        $table->string('code')->nullable();
    });

    $customer = Customer::query()->create(['name' => 'Dispatch Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'missing-customer@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Missing customer delivery']);
    $deliveryNote->forceFill([
        'code' => 'DN-1001',
        'customer_id' => null,
    ])->save();

    expect(fn () => app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: $deliveryNote->id,
        dispatchedBy: $dispatcher->id,
        quantity: 1,
        customerId: $customer->id,
    )))->toThrow(
        DomainException::class,
        'Delivery note DN-1001 does not have a customer Dispatch Customer.',
    );

    expect(Dispatch::query()->doesntExist())->toBeTrue();
});

it('posts a cross return only on independent approval and credits the returning customer', function (): void {
    Event::fake([
        ContainerReturned::class,
        ContainerReturnApproved::class,
        ContainerReturnSubmitted::class,
    ]);

    $dispatchCustomer = Customer::query()->create(['name' => 'Dispatch Customer']);
    $returnCustomer = Customer::query()->create(['name' => 'Returning Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'cross-dispatch@example.com']);
    $preparer = User::query()->create(['name' => 'Preparer', 'email' => 'cross-prepare@example.com']);
    $approver = User::query()->create(['name' => 'Approver', 'email' => 'cross-approve@example.com']);
    $container = Container::factory()->create();
    $dispatch = createApprovedDispatchForReturn($dispatchCustomer, $dispatcher, $container);
    $dispatchEntry = soleDispatchEntry($dispatch);

    $containerReturn = createSubmittedReturn($returnCustomer, $preparer, $container);

    expect(Container::query()->availableForDispatch()->whereKey($container)->doesntExist())->toBeTrue()
        ->and(fn () => app(ApproveContainerReturnAction::class)->handle(
            $containerReturn,
            $preparer->id,
        ))->toThrow(
            DomainException::class,
            'The preparer cannot approve their own container return.',
        );

    $approved = app(ApproveContainerReturnAction::class)->handle($containerReturn, $approver->id);
    $entry = soleReturnEntry($approved);

    expect((string) $approved->state)->toBe('approved')
        ->and($approved->approved_by)->toBe($approver->id)
        ->and($entry->dispatch_entry_id)->toBe($dispatchEntry->id)
        ->and($entry->cross_return)->toBeTrue()
        ->and(Container::query()->availableForDispatch()->whereKey($container)->exists())->toBeTrue();

    $dispatchBalance = app(CustomerContainerBalanceQuery::class)->forCustomer($dispatchCustomer->id);
    $returnBalance = app(CustomerContainerBalanceQuery::class)->forCustomer($returnCustomer->id);

    expect($dispatchBalance->dispatched)->toBe(1)
        ->and($dispatchBalance->returned)->toBe(0)
        ->and($dispatchBalance->outstanding)->toBe(1)
        ->and($returnBalance->dispatched)->toBe(0)
        ->and($returnBalance->returned)->toBe(1)
        ->and($returnBalance->outstanding)->toBe(-1);

    Event::assertDispatched(ContainerReturned::class);
    Event::assertDispatched(ContainerReturnApproved::class);
    Event::assertDispatched(ContainerReturnSubmitted::class);
});

it('reconciles repeat dispatch and return cycles to distinct source entries', function (): void {
    $customer = Customer::query()->create(['name' => 'Repeat Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'repeat-dispatch@example.com']);
    $preparer = User::query()->create(['name' => 'Preparer', 'email' => 'repeat-prepare@example.com']);
    $approver = User::query()->create(['name' => 'Approver', 'email' => 'repeat-approve@example.com']);
    $container = Container::factory()->create();

    $firstDispatch = createApprovedDispatchForReturn($customer, $dispatcher, $container, '2026-07-01');
    $firstReturn = createSubmittedReturn($customer, $preparer, $container, date: '2026-07-02');
    app(ApproveContainerReturnAction::class)->handle($firstReturn, $approver->id);

    $secondDispatch = createApprovedDispatchForReturn($customer, $dispatcher, $container, '2026-07-03');
    $secondReturn = createSubmittedReturn($customer, $preparer, $container, date: '2026-07-04');
    app(ApproveContainerReturnAction::class)->handle($secondReturn, $approver->id);

    expect(ContainerReturnEntry::query()->orderBy('id')->pluck('dispatch_entry_id')->all())->toBe([
        soleDispatchEntry($firstDispatch)->id,
        soleDispatchEntry($secondDispatch)->id,
    ])
        ->and(Container::query()->availableForDispatch()->whereKey($container)->exists())->toBeTrue();
});

it('emits damage and loss events and closes lost-container custody', function (): void {
    Event::fake([
        ContainerReturned::class,
        ContainerDamaged::class,
        ContainerLost::class,
    ]);

    $customer = Customer::query()->create(['name' => 'Condition Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'condition-dispatch@example.com']);
    $preparer = User::query()->create(['name' => 'Preparer', 'email' => 'condition-prepare@example.com']);
    $approver = User::query()->create(['name' => 'Approver', 'email' => 'condition-approve@example.com']);
    $damaged = Container::factory()->create();
    $lost = Container::factory()->create();

    createApprovedDispatchForReturn($customer, $dispatcher, $damaged);
    createApprovedDispatchForReturn($customer, $dispatcher, $lost);

    app(ApproveContainerReturnAction::class)->handle(
        createSubmittedReturn($customer, $preparer, $damaged, ReturnCondition::Damaged),
        $approver->id,
    );
    app(ApproveContainerReturnAction::class)->handle(
        createSubmittedReturn($customer, $preparer, $lost, ReturnCondition::Lost),
        $approver->id,
    );

    expect($lost->refresh()->is_active)->toBeFalse()
        ->and(Container::query()->currentlyDispatched()->whereKey($lost)->doesntExist())->toBeTrue();

    Event::assertDispatchedTimes(ContainerReturned::class, 1);
    Event::assertDispatched(ContainerDamaged::class);
    Event::assertDispatched(ContainerLost::class);
});

it('rolls approval back when a return predates its source dispatch', function (): void {
    $customer = Customer::query()->create(['name' => 'Date Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'date-dispatch@example.com']);
    $preparer = User::query()->create(['name' => 'Preparer', 'email' => 'date-prepare@example.com']);
    $approver = User::query()->create(['name' => 'Approver', 'email' => 'date-approve@example.com']);
    $container = Container::factory()->create();

    createApprovedDispatchForReturn($customer, $dispatcher, $container, '2026-07-10');
    $containerReturn = createSubmittedReturn($customer, $preparer, $container, date: '2026-07-09');

    expect(fn () => app(ApproveContainerReturnAction::class)->handle(
        $containerReturn,
        $approver->id,
    ))->toThrow(
        DomainException::class,
        "Return date for container [{$container->serial}] cannot be earlier than its dispatch date.",
    );

    expect((string) $containerReturn->refresh()->state)->toBe('submitted')
        ->and($containerReturn->approved_at)->toBeNull()
        ->and(soleReturnEntry($containerReturn)->dispatch_entry_id)->toBeNull();
});

it('returns submitted documents to draft without posting custody', function (): void {
    $customer = Customer::query()->create(['name' => 'Correction Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'correction-dispatch@example.com']);
    $preparer = User::query()->create(['name' => 'Preparer', 'email' => 'correction-prepare@example.com']);
    $container = Container::factory()->create();

    createApprovedDispatchForReturn($customer, $dispatcher, $container);
    $submitted = createSubmittedReturn($customer, $preparer, $container);
    $draft = app(ReturnContainerReturnToDraftAction::class)->handle($submitted);

    expect((string) $draft->state)->toBe('draft')
        ->and(soleReturnEntry($draft)->dispatch_entry_id)->toBeNull()
        ->and(Container::query()->availableForDispatch()->whereKey($container)->doesntExist())->toBeTrue();
});

it('checks for duplicate submitted entries unless the check is disabled', function (): void {
    $customer = Customer::query()->create(['name' => 'Duplicate Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'duplicate-dispatch@example.com']);
    $preparer = User::query()->create(['name' => 'Preparer', 'email' => 'duplicate-prepare@example.com']);
    $container = Container::factory()->create();

    createApprovedDispatchForReturn($customer, $dispatcher, $container);
    createSubmittedReturn($customer, $preparer, $container);
    $duplicate = app(CreateContainerReturnAction::class)->handle(new CreateContainerReturnData(
        customerId: $customer->id,
        userId: $preparer->id,
        transactionDate: '2026-07-02',
    ));
    app(AddContainerReturnEntryAction::class)->handle(
        $duplicate,
        new AddContainerReturnEntryData(
            containerId: $container->id,
            condition: ReturnCondition::Good,
        ),
    );

    expect(fn () => app(SubmitContainerReturnAction::class)->handle($duplicate))
        ->toThrow(
            DomainException::class,
            'One or more containers are already included in another submitted return.',
        );

    expect((string) $duplicate->refresh()->state)->toBe('draft');

    $submitted = app(SubmitContainerReturnAction::class)->handle(
        $duplicate,
        checkForDuplicateSubmittedEntries: false,
    );

    expect((string) $submitted->state)->toBe('submitted');
});

it('rejects returns without outstanding custody and blocks voiding posted dispatches', function (): void {
    $customer = Customer::query()->create(['name' => 'Control Customer']);
    $dispatcher = User::query()->create(['name' => 'Dispatcher', 'email' => 'control-dispatch@example.com']);
    $preparer = User::query()->create(['name' => 'Preparer', 'email' => 'control-prepare@example.com']);
    $approver = User::query()->create(['name' => 'Approver', 'email' => 'control-approve@example.com']);
    $unassigned = Container::factory()->create();
    $invalidReturn = createSubmittedReturn($customer, $preparer, $unassigned);

    expect(fn () => app(ApproveContainerReturnAction::class)->handle(
        $invalidReturn,
        $approver->id,
    ))->toThrow(
        DomainException::class,
        "Container [{$unassigned->serial}] has no outstanding approved dispatch.",
    );

    $approvedWithoutOutstandingCheck = app(ApproveContainerReturnAction::class)->handle(
        $invalidReturn,
        $approver->id,
        checkForOutstandingEntries: false,
    );

    expect((string) $approvedWithoutOutstandingCheck->state)->toBe('approved')
        ->and(soleReturnEntry($approvedWithoutOutstandingCheck)->dispatch_entry_id)->toBeNull();

    $returned = Container::factory()->create();
    $dispatch = createApprovedDispatchForReturn($customer, $dispatcher, $returned);
    app(ApproveContainerReturnAction::class)->handle(
        createSubmittedReturn($customer, $preparer, $returned),
        $approver->id,
    );

    expect(fn () => app(VoidDispatchAction::class)->handle(
        $dispatch,
        new VoidDispatchData(
            voidedBy: $approver->id,
            reason: 'Attempted reversal',
        ),
    ))->toThrow(
        DomainException::class,
        'Dispatches with return activity require a reversal workflow instead of voiding.',
    );

    expect((string) $dispatch->refresh()->state)->toBe('approved');
});
