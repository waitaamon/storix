<?php

declare(strict_types=1);

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Storix\Filament\Resources\DispatchResources\Pages\ViewDispatch;
use Storix\Filament\Resources\DispatchResources\RelationManagers\ContainersRelationManager;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

it('adds multiple selected containers to a draft dispatch', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);

    $user = User::query()->create(['name' => 'Relation Manager', 'email' => 'relation-manager@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Multiple container delivery']);
    $containers = Container::factory()->count(3)->create();
    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 3,
    ]);

    actingAs($user);

    $component = Livewire::test(ContainersRelationManager::class, [
        'ownerRecord' => $dispatch,
        'pageClass' => ViewDispatch::class,
    ])->assertTableActionVisible('addContainers');
    $manager = $component->instance();

    if (! $manager instanceof ContainersRelationManager) {
        throw new LogicException('The Livewire component is not a containers relation manager.');
    }

    $manager->mountTableAction('addContainers');
    $manager->getMountedTableActionForm()?->fill([
        'container_ids' => $containers->modelKeys(),
    ]);
    $manager->callMountedTableAction();

    expect($dispatch->entries()->pluck('container_id')->sort()->values()->all())
        ->toBe($containers->modelKeys());
});

it('configures the add action and available options from the container label', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);
    config()->set('storix.labels.container', 'gas cylinder');

    $user = User::query()->create(['name' => 'Configured Relation Manager', 'email' => 'configured-relation@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Configured relation delivery']);
    $availableContainer = Container::factory()->create();
    $reservedContainer = Container::factory()->create();
    $ownerDispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
    ]);
    $otherDispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'quantity' => 1,
    ]);

    DispatchEntry::query()->create([
        'dispatch_id' => $otherDispatch->id,
        'container_id' => $reservedContainer->id,
    ]);

    actingAs($user);

    $component = Livewire::test(ContainersRelationManager::class, [
        'ownerRecord' => $ownerDispatch,
        'pageClass' => ViewDispatch::class,
    ]);
    $manager = $component->instance();

    if (! $manager instanceof ContainersRelationManager) {
        throw new LogicException('The Livewire component is not a containers relation manager.');
    }

    $action = $manager->getTable()->getAction('addContainers');

    expect($action)->toBeInstanceOf(CreateAction::class);

    if (! $action instanceof CreateAction) {
        throw new LogicException('The add containers action is not a create action.');
    }

    $schema = $action->getSchema(Schema::make($manager));
    $containerSelect = $schema?->getFlatComponents(withHidden: true)['container_ids'] ?? null;

    expect($action->getLabel())->toBe('Add')
        ->and($action->getModalHeading())->toBe('Add Gas Cylinders')
        ->and($action->getModalSubmitActionLabel())->toBe('Add')
        ->and($action->canCreateAnother())->toBeFalse()
        ->and($containerSelect)->toBeInstanceOf(Select::class);

    if (! $containerSelect instanceof Select) {
        throw new LogicException('The container IDs component is not a select.');
    }

    expect($containerSelect->getLabel())->toBe('Gas Cylinders')
        ->and($containerSelect->isMultiple())->toBeTrue()
        ->and($containerSelect->isRequired())->toBeTrue()
        ->and($containerSelect->getOptions())->toHaveKey($availableContainer->id)
        ->and($containerSelect->getOptions())->not->toHaveKey($reservedContainer->id);
});
