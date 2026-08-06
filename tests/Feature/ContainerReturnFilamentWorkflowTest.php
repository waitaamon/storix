<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Storix\Actions\ApproveDispatchAction;
use Storix\Actions\CreateDispatchAction;
use Storix\Data\CreateDispatchData;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\ContainerReturnResources\Pages\CreateContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\Pages\ViewContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\RelationManagers\EntriesRelationManager;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

/** @param Testable<ViewContainerReturn> $component */
function returnViewPage(Testable $component): ViewContainerReturn
{
    return $component->instance();
}

function createFilamentReturnDispatch(
    Customer $customer,
    User $dispatcher,
    Container $container,
): Dispatch {
    $dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
        deliveryNoteId: null,
        dispatchedBy: $dispatcher->id,
        quantity: 1,
        customerId: $customer->id,
        dispatchedAt: '2026-07-01',
        containerIds: [$container->id],
    ));

    return app(ApproveDispatchAction::class)->handle($dispatch, $dispatcher->id);
}

beforeEach(function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);
    Filament::setCurrentPanel('test');
});

it('creates, prepares, submits, and independently approves a return through Filament', function (): void {
    $customer = Customer::query()->create(['name' => 'Filament Customer']);
    $dispatcher = User::query()->create([
        'name' => 'Dispatcher',
        'email' => 'filament-dispatcher@example.com',
    ]);
    $preparer = User::query()->create([
        'name' => 'Preparer',
        'email' => 'filament-preparer@example.com',
    ]);
    $approver = User::query()->create([
        'name' => 'Approver',
        'email' => 'filament-approver@example.com',
    ]);
    $container = Container::factory()->create();
    createFilamentReturnDispatch($customer, $dispatcher, $container);

    actingAs($preparer);

    Livewire::test(CreateContainerReturn::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'transaction_date' => '2026-07-02',
            'note' => 'Prepared through the return document page.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $containerReturn = ContainerReturn::query()->sole();

    $relationComponent = Livewire::test(EntriesRelationManager::class, [
        'ownerRecord' => $containerReturn,
        'pageClass' => ViewContainerReturn::class,
    ]);
    $relationManager = $relationComponent->instance();

    if (! $relationManager instanceof EntriesRelationManager) {
        throw new LogicException('The Livewire component is not a return entries relation manager.');
    }

    $relationManager->mountTableAction('create');
    $relationManager->getMountedTableActionForm()?->fill([
        'container_id' => $container->id,
        'return_condition' => ReturnCondition::Good->value,
        'note' => 'Seal intact.',
    ]);
    $relationManager->callMountedTableAction();

    expect($containerReturn->entries()->count())->toBe(1);

    $submitComponent = Livewire::test(
        ViewContainerReturn::class,
        ['record' => $containerReturn->getRouteKey()],
    )->assertActionVisible('submit');
    $submitPage = returnViewPage($submitComponent);
    $submitPage->mountAction('submit');
    $submitPage->callMountedAction();

    expect((string) $containerReturn->refresh()->state)->toBe('submitted');

    $returnToDraftComponent = Livewire::test(
        ViewContainerReturn::class,
        ['record' => $containerReturn->getRouteKey()],
    )
        ->assertActionHidden('approve')
        ->assertActionVisible('returnToDraft');
    $returnToDraftPage = returnViewPage($returnToDraftComponent);
    $returnToDraftPage->mountAction('returnToDraft');
    $returnToDraftPage->callMountedAction();

    expect((string) $containerReturn->refresh()->state)->toBe('draft');

    $resubmitComponent = Livewire::test(
        ViewContainerReturn::class,
        ['record' => $containerReturn->getRouteKey()],
    );
    $resubmitPage = returnViewPage($resubmitComponent);
    $resubmitPage->mountAction('submit');
    $resubmitPage->callMountedAction();

    expect((string) $containerReturn->refresh()->state)->toBe('submitted');

    actingAs($approver);

    $approveComponent = Livewire::test(
        ViewContainerReturn::class,
        ['record' => $containerReturn->getRouteKey()],
    )->assertActionVisible('approve');
    $approvePage = returnViewPage($approveComponent);
    $approvePage->mountAction('approve');
    $approvePage->callMountedAction();

    $containerReturn->refresh();
    $entry = ContainerReturnEntry::query()
        ->where('container_return_id', $containerReturn->id)
        ->sole();

    expect((string) $containerReturn->state)->toBe('approved')
        ->and($containerReturn->approved_by)->toBe($approver->id)
        ->and($entry->dispatch_entry_id)->not->toBeNull()
        ->and($entry->cross_return)->toBeFalse()
        ->and(Container::query()->availableForDispatch()->whereKey($container)->exists())->toBeTrue();
});

it('updates and removes draft entries through the return relation manager', function (): void {
    $customer = Customer::query()->create(['name' => 'Draft Entry Customer']);
    $dispatcher = User::query()->create([
        'name' => 'Entry Dispatcher',
        'email' => 'entry-dispatcher@example.com',
    ]);
    $preparer = User::query()->create([
        'name' => 'Entry Preparer',
        'email' => 'entry-preparer@example.com',
    ]);
    $container = Container::factory()->create();
    createFilamentReturnDispatch($customer, $dispatcher, $container);
    $containerReturn = ContainerReturn::factory()->draft()->create([
        'customer_id' => $customer->id,
        'user_id' => $preparer->id,
    ]);

    actingAs($preparer);

    $manager = Livewire::test(EntriesRelationManager::class, [
        'ownerRecord' => $containerReturn,
        'pageClass' => ViewContainerReturn::class,
    ])->instance();

    if (! $manager instanceof EntriesRelationManager) {
        throw new LogicException('The Livewire component is not a return entries relation manager.');
    }

    $manager->mountTableAction('create');
    $manager->getMountedTableActionForm()?->fill([
        'container_id' => $container->id,
        'return_condition' => ReturnCondition::Good->value,
    ]);
    $manager->callMountedTableAction();
    $manager->unmountTableAction();

    $entry = $containerReturn->entries()->sole();

    $manager->mountTableAction('edit', (string) $entry->getKey());
    $manager->getMountedTableActionForm()?->fill([
        'container_id' => $container->id,
        'return_condition' => ReturnCondition::Damaged->value,
        'note' => 'Damaged cap.',
    ]);
    $manager->callMountedTableAction();
    $manager->unmountTableAction();

    expect($entry->refresh()->return_condition)->toBe(ReturnCondition::Damaged)
        ->and($entry->note)->toBe('Damaged cap.');

    $deleteManager = Livewire::test(EntriesRelationManager::class, [
        'ownerRecord' => $containerReturn,
        'pageClass' => ViewContainerReturn::class,
    ])->instance();

    if (! $deleteManager instanceof EntriesRelationManager) {
        throw new LogicException('The Livewire component is not a return entries relation manager.');
    }

    $deleteManager->mountTableAction('delete', (string) $entry->getKey());
    $deleteManager->callMountedTableAction();

    expect($containerReturn->entries()->doesntExist())->toBeTrue();
});
