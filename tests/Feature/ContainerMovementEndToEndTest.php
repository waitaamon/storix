<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Storix\Enums\ContainerMovementType;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\ContainerResources\Pages\ViewContainer;
use Storix\Filament\Resources\ContainerResources\RelationManagers\MovementsRelationManager;
use Storix\Filament\Resources\ContainerReturnResources\Pages\CreateContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\Pages\ViewContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\RelationManagers\EntriesRelationManager;
use Storix\Filament\Resources\DispatchResources\Pages\CreateDispatch;
use Storix\Filament\Resources\DispatchResources\Pages\ViewDispatch;
use Storix\Models\Container;
use Storix\Models\ContainerMovement;
use Storix\Models\ContainerReturn;
use Storix\Models\Dispatch;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

it('tracks dispatch return loss and redispatch as independent filament movement events', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);
    Filament::setCurrentPanel('test');

    $sourceCustomer = Customer::query()->create(['name' => 'Movement E2E Source']);
    $returningCustomer = Customer::query()->create(['name' => 'Movement E2E Returning']);
    $dispatcher = User::query()->create([
        'name' => 'Movement E2E Dispatcher',
        'email' => 'movement-e2e-dispatcher@example.com',
    ]);
    $preparer = User::query()->create([
        'name' => 'Movement E2E Preparer',
        'email' => 'movement-e2e-preparer@example.com',
    ]);
    $approver = User::query()->create([
        'name' => 'Movement E2E Approver',
        'email' => 'movement-e2e-approver@example.com',
    ]);
    $returnedContainer = Container::factory()->create();
    $lostContainer = Container::factory()->create();

    actingAs($dispatcher);

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'customer_id' => $sourceCustomer->id,
            'delivery_note_id' => null,
            'dispatched_at' => '2026-08-01 09:00:00',
            'quantity' => 2,
            'container_ids' => [$returnedContainer->id, $lostContainer->id],
            'dispatch_note' => 'Movement lifecycle dispatch.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sourceDispatch = Dispatch::query()->sole();
    $dispatchPage = Livewire::test(ViewDispatch::class, [
        'record' => $sourceDispatch->getRouteKey(),
    ])->instance();

    if (! $dispatchPage instanceof ViewDispatch) {
        throw new LogicException('The Livewire component is not a dispatch view page.');
    }

    $dispatchPage->mountAction('approve');
    $dispatchPage->callMountedAction();

    $initialMovements = ContainerMovement::query()
        ->whereIn('container_id', [$returnedContainer->id, $lostContainer->id])
        ->get();

    expect($initialMovements)->toHaveCount(2)
        ->and($initialMovements->pluck('document_type')->all())->each
        ->toBe(ContainerMovementType::Dispatch)
        ->and($initialMovements->pluck('customer_id')->unique()->values()->all())
        ->toBe([$sourceCustomer->id])
        ->and($initialMovements->pluck('cross_return')->filter()->isEmpty())->toBeTrue();

    actingAs($preparer);

    Livewire::test(CreateContainerReturn::class)
        ->fillForm([
            'customer_id' => $returningCustomer->id,
            'transaction_date' => '2026-08-02',
            'note' => 'Cross-customer movement return and loss.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $containerReturn = ContainerReturn::query()->sole();
    $entriesManager = Livewire::test(EntriesRelationManager::class, [
        'ownerRecord' => $containerReturn,
        'pageClass' => ViewContainerReturn::class,
    ])->instance();

    if (! $entriesManager instanceof EntriesRelationManager) {
        throw new LogicException('The Livewire component is not a return entries relation manager.');
    }

    $entriesManager->mountTableAction('create');
    $entriesManager->getMountedTableActionForm()?->fill([
        'container_id' => $returnedContainer->id,
        'return_condition' => ReturnCondition::Good->value,
        'note' => 'Returned serviceable.',
    ]);
    $entriesManager->callMountedTableAction();

    $entriesManager->mountTableAction('create');
    $entriesManager->getMountedTableActionForm()?->fill([
        'container_id' => $lostContainer->id,
        'return_condition' => ReturnCondition::Lost->value,
        'note' => 'Customer reported container lost.',
    ]);
    $entriesManager->callMountedTableAction();

    $returnPage = Livewire::test(ViewContainerReturn::class, [
        'record' => $containerReturn->getRouteKey(),
    ])->instance();

    if (! $returnPage instanceof ViewContainerReturn) {
        throw new LogicException('The Livewire component is not a container return view page.');
    }

    $returnPage->mountAction('submit');
    $returnPage->callMountedAction();

    actingAs($approver);

    $approvalPage = Livewire::test(ViewContainerReturn::class, [
        'record' => $containerReturn->getRouteKey(),
    ])->instance();

    if (! $approvalPage instanceof ViewContainerReturn) {
        throw new LogicException('The Livewire component is not a container return approval page.');
    }

    $approvalPage->mountAction('approve');
    $approvalPage->callMountedAction();

    $returnedEvents = ContainerMovement::query()
        ->with('customer')
        ->where('container_id', $returnedContainer->id)
        ->orderBy('movement_date')
        ->get();
    $lostEvents = ContainerMovement::query()
        ->with('customer')
        ->where('container_id', $lostContainer->id)
        ->orderBy('movement_date')
        ->get();
    $returnedReturnEvent = $returnedEvents->last();
    $lostReturnEvent = $lostEvents->last();

    expect($returnedEvents)->toHaveCount(2)
        ->and($returnedReturnEvent?->document_type)->toBe(ContainerMovementType::Return)
        ->and($returnedReturnEvent?->document_code)->toBe($containerReturn->code)
        ->and($returnedReturnEvent?->customer->is($returningCustomer))->toBeTrue()
        ->and($returnedReturnEvent?->cross_return)->toBeTrue()
        ->and($lostEvents)->toHaveCount(2)
        ->and($lostReturnEvent?->document_type)->toBe(ContainerMovementType::Return)
        ->and($lostReturnEvent?->customer->is($returningCustomer))->toBeTrue()
        ->and($lostReturnEvent?->cross_return)->toBeTrue()
        ->and($returnedContainer->refresh()->is_active)->toBeTrue()
        ->and($lostContainer->refresh()->is_active)->toBeFalse();

    actingAs($dispatcher);

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'customer_id' => $returningCustomer->id,
            'delivery_note_id' => null,
            'dispatched_at' => '2026-08-03 09:00:00',
            'quantity' => 1,
            'container_ids' => [$returnedContainer->id],
            'dispatch_note' => 'Redispatched after approved return.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $redispatch = Dispatch::query()
        ->whereKeyNot($sourceDispatch->id)
        ->sole();
    $redispatchPage = Livewire::test(ViewDispatch::class, [
        'record' => $redispatch->getRouteKey(),
    ])->instance();

    if (! $redispatchPage instanceof ViewDispatch) {
        throw new LogicException('The Livewire component is not a redispatch view page.');
    }

    $redispatchPage->mountAction('approve');
    $redispatchPage->callMountedAction();

    $returnedContainerEvents = ContainerMovement::query()
        ->where('container_id', $returnedContainer->id)
        ->orderBy('movement_date')
        ->get();
    $redispatchEvent = $returnedContainerEvents->last();

    expect($returnedContainerEvents)->toHaveCount(3)
        ->and($returnedContainerEvents->pluck('document_type')->values()->all())->toBe([
            ContainerMovementType::Dispatch,
            ContainerMovementType::Return,
            ContainerMovementType::Dispatch,
        ])->and($redispatchEvent?->customer_id)->toBe($returningCustomer->id)
        ->and($redispatchEvent?->document_code)->toBe($redispatch->code)
        ->and($redispatchEvent?->cross_return)->toBeNull();

    $movementsManager = Livewire::test(MovementsRelationManager::class, [
        'ownerRecord' => $returnedContainer,
        'pageClass' => ViewContainer::class,
    ]);

    $movementsManager->assertCanSeeTableRecords($returnedContainerEvents);

    $manager = $movementsManager->instance();

    if (! $manager instanceof MovementsRelationManager) {
        throw new LogicException('The Livewire component is not a movements relation manager.');
    }

    expect($manager->getTableQueryForExport()->pluck('id')->sort()->values()->all())
        ->toBe(collect($returnedContainerEvents->modelKeys())->sort()->values()->all());
});
