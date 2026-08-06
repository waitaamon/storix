<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\ContainerReturnResources\Pages\CreateContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\Pages\ViewContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\RelationManagers\EntriesRelationManager;
use Storix\Filament\Resources\DispatchResources\Pages\CreateDispatch;
use Storix\Filament\Resources\DispatchResources\Pages\ViewDispatch;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Support\CustomerContainerBalanceQuery;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

/** @param Testable<ViewDispatch> $component */
function e2eDispatchViewPage(Testable $component): ViewDispatch
{
    return $component->instance();
}

/** @param Testable<ViewContainerReturn> $component */
function e2eReturnViewPage(Testable $component): ViewContainerReturn
{
    return $component->instance();
}

it('completes a customer dispatch, cross return, and redispatch through Filament', function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);
    Filament::setCurrentPanel('test');

    $sourceCustomer = Customer::query()->create(['name' => 'Source Customer']);
    $returningCustomer = Customer::query()->create(['name' => 'Returning Customer']);
    $dispatcher = User::query()->create([
        'name' => 'E2E Dispatcher',
        'email' => 'e2e-dispatcher@example.com',
    ]);
    $preparer = User::query()->create([
        'name' => 'E2E Preparer',
        'email' => 'e2e-preparer@example.com',
    ]);
    $approver = User::query()->create([
        'name' => 'E2E Approver',
        'email' => 'e2e-approver@example.com',
    ]);
    $container = Container::factory()->create();

    actingAs($dispatcher);

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'customer_id' => $sourceCustomer->id,
            'delivery_note_id' => null,
            'dispatched_at' => '2026-07-01 09:00:00',
            'quantity' => 1,
            'container_ids' => [$container->id],
            'dispatch_note' => 'Direct dispatch without a delivery note.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sourceDispatch = Dispatch::query()->sole();
    $dispatchApproval = Livewire::test(
        ViewDispatch::class,
        ['record' => $sourceDispatch->getRouteKey()],
    )->assertActionVisible('approve');
    $dispatchApprovalPage = e2eDispatchViewPage($dispatchApproval);
    $dispatchApprovalPage->mountAction('approve');
    $dispatchApprovalPage->callMountedAction();

    expect((string) $sourceDispatch->refresh()->state)->toBe('approved')
        ->and($sourceDispatch->delivery_note_id)->toBeNull()
        ->and(Container::query()->availableForDispatch()->whereKey($container)->doesntExist())->toBeTrue();

    actingAs($preparer);

    Livewire::test(CreateContainerReturn::class)
        ->fillForm([
            'customer_id' => $returningCustomer->id,
            'transaction_date' => '2026-07-02',
            'note' => 'Cross-customer physical return.',
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
        'container_id' => $container->id,
        'return_condition' => ReturnCondition::Good->value,
        'note' => 'Returned by another customer.',
    ]);
    $entriesManager->callMountedTableAction();

    $submit = Livewire::test(
        ViewContainerReturn::class,
        ['record' => $containerReturn->getRouteKey()],
    )->assertActionVisible('submit');
    $submitPage = e2eReturnViewPage($submit);
    $submitPage->mountAction('submit');
    $submitPage->callMountedAction();

    expect((string) $containerReturn->refresh()->state)->toBe('submitted');

    Livewire::test(
        ViewContainerReturn::class,
        ['record' => $containerReturn->getRouteKey()],
    )->assertActionHidden('approve');

    actingAs($approver);

    $returnApproval = Livewire::test(
        ViewContainerReturn::class,
        ['record' => $containerReturn->getRouteKey()],
    )->assertActionVisible('approve');
    $returnApprovalPage = e2eReturnViewPage($returnApproval);
    $returnApprovalPage->mountAction('approve');
    $returnApprovalPage->callMountedAction();

    $containerReturn->refresh();
    $returnEntry = ContainerReturnEntry::query()
        ->where('container_return_id', $containerReturn->id)
        ->sole();
    $sourceEntryId = $sourceDispatch->entries()->sole()->getKey();

    expect((string) $containerReturn->state)->toBe('approved')
        ->and($containerReturn->approved_by)->toBe($approver->id)
        ->and($returnEntry->cross_return)->toBeTrue()
        ->and($returnEntry->dispatch_entry_id)->toBe($sourceEntryId)
        ->and(Container::query()->availableForDispatch()->whereKey($container)->exists())->toBeTrue();

    $sourceBalance = app(CustomerContainerBalanceQuery::class)->forCustomer($sourceCustomer->id);
    $returningBalance = app(CustomerContainerBalanceQuery::class)->forCustomer($returningCustomer->id);

    expect($sourceBalance->dispatched)->toBe(1)
        ->and($sourceBalance->returned)->toBe(0)
        ->and($sourceBalance->outstanding)->toBe(1)
        ->and($returningBalance->dispatched)->toBe(0)
        ->and($returningBalance->returned)->toBe(1)
        ->and($returningBalance->outstanding)->toBe(-1);

    actingAs($dispatcher);

    Livewire::test(CreateDispatch::class)
        ->fillForm([
            'customer_id' => $returningCustomer->id,
            'delivery_note_id' => null,
            'dispatched_at' => '2026-07-03 09:00:00',
            'quantity' => 1,
            'container_ids' => [$container->id],
            'dispatch_note' => 'Redispatched after approved return.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $redispatch = Dispatch::query()
        ->whereKeyNot($sourceDispatch->id)
        ->sole();
    $redispatchApproval = Livewire::test(
        ViewDispatch::class,
        ['record' => $redispatch->getRouteKey()],
    )->assertActionVisible('approve');
    $redispatchApprovalPage = e2eDispatchViewPage($redispatchApproval);
    $redispatchApprovalPage->mountAction('approve');
    $redispatchApprovalPage->callMountedAction();

    $returningBalance = app(CustomerContainerBalanceQuery::class)->forCustomer($returningCustomer->id);

    expect((string) $redispatch->refresh()->state)->toBe('approved')
        ->and($returningBalance->dispatched)->toBe(1)
        ->and($returningBalance->returned)->toBe(1)
        ->and($returningBalance->outstanding)->toBe(0)
        ->and(Container::query()->currentlyDispatched()->whereKey($container)->exists())->toBeTrue()
        ->and(Container::query()->availableForDispatch()->whereKey($container)->doesntExist())->toBeTrue();
});
