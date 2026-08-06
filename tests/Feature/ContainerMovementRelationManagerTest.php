<?php

declare(strict_types=1);

use Filament\Actions\ExportBulkAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Storix\Enums\ContainerMovementType;
use Storix\Filament\Exports\ContainerMovementExporter;
use Storix\Filament\Resources\ContainerResources\ContainerResource;
use Storix\Filament\Resources\ContainerResources\Pages\ViewContainer;
use Storix\Filament\Resources\ContainerResources\RelationManagers\DispatchesRelationManager;
use Storix\Filament\Resources\ContainerResources\RelationManagers\MovementsRelationManager;
use Storix\Filament\Resources\ContainerResources\RelationManagers\ReturnsRelationManager;
use Storix\Models\Container;
use Storix\Models\ContainerMovement;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Filament::setCurrentPanel('test');
});

it('configures exactly the requested immutable movement event columns', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);

    $user = User::query()->create([
        'name' => 'Movement History User',
        'email' => 'movement-history@example.com',
    ]);
    $container = Container::factory()->create();

    actingAs($user);

    $manager = Livewire::test(MovementsRelationManager::class, [
        'ownerRecord' => $container,
        'pageClass' => ViewContainer::class,
    ])->instance();

    if (! $manager instanceof MovementsRelationManager) {
        throw new LogicException('The Livewire component is not a movements relation manager.');
    }

    $table = $manager->getTable();
    $export = $table->getBulkAction('export');

    expect(ContainerResource::getRelations())->toContain(
        DispatchesRelationManager::class,
        ReturnsRelationManager::class,
        MovementsRelationManager::class,
    )->and(MovementsRelationManager::getTitle($container, ViewContainer::class))->toBe('Movements')
        ->and($manager->isReadOnly())->toBeTrue()
        ->and(array_keys($table->getColumns()))->toBe([
            'movement_date',
            'customer.name',
            'document_type',
            'document_code',
            'cross_return',
        ])->and($table->getColumn('movement_date'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('customer.name'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('document_type'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('document_code'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('cross_return'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('status'))->toBeNull()
        ->and($table->getFilter('document_type'))->toBeInstanceOf(SelectFilter::class)
        ->and($table->getFilter('cross_return'))->toBeInstanceOf(TernaryFilter::class)
        ->and($table->getDefaultSortColumn())->toBe('movement_date')
        ->and($table->getDefaultSortDirection())->toBe('desc')
        ->and($export)->toBeInstanceOf(ExportBulkAction::class);

    if (! $export instanceof ExportBulkAction) {
        throw new LogicException('The movement history export action is not configured.');
    }

    expect($export->getExporter())->toBe(ContainerMovementExporter::class);
});

it('filters owner-scoped return events and exports legacy unlinked returns', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);

    $user = User::query()->create([
        'name' => 'Movement Filter User',
        'email' => 'movement-filter@example.com',
    ]);
    $dispatchCustomer = Customer::query()->create(['name' => 'Movement Dispatch Customer']);
    $returnCustomer = Customer::query()->create(['name' => 'Movement Return Customer']);
    $owner = Container::factory()->create();
    $other = Container::factory()->create();
    $ownerDispatch = Dispatch::factory()->create([
        'customer_id' => $dispatchCustomer->id,
        'quantity' => 1,
        'state' => 'approved',
        'approved_at' => now(),
    ]);
    $ownerDispatchEntry = DispatchEntry::factory()->create([
        'container_id' => $owner->id,
        'dispatch_id' => $ownerDispatch->id,
    ]);
    DispatchEntry::factory()->create([
        'container_id' => $other->id,
        'dispatch_id' => Dispatch::factory()->create([
            'quantity' => 1,
            'state' => 'approved',
            'approved_at' => now(),
        ])->id,
    ]);
    $containerReturn = ContainerReturn::factory()->approved()->create([
        'customer_id' => $returnCustomer->id,
    ]);
    $returnEntry = ContainerReturnEntry::factory()->crossReturn()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $owner->id,
        'dispatch_entry_id' => null,
    ]);

    $dispatchMovement = ContainerMovement::query()->findOrFail("dispatch:{$ownerDispatchEntry->id}");
    $returnMovement = ContainerMovement::query()->findOrFail("return:{$returnEntry->id}");

    actingAs($user);

    $component = Livewire::test(MovementsRelationManager::class, [
        'ownerRecord' => $owner,
        'pageClass' => ViewContainer::class,
    ]);

    $component
        ->filterTable('document_type', ContainerMovementType::Return->value)
        ->filterTable('cross_return', '1')
        ->assertCanSeeTableRecords([$returnMovement])
        ->assertCanNotSeeTableRecords([$dispatchMovement]);

    $manager = $component->instance();

    if (! $manager instanceof MovementsRelationManager) {
        throw new LogicException('The Livewire component is not a movements relation manager.');
    }

    expect($manager->getTableQueryForExport()->pluck('id')->all())
        ->toBe(["return:{$returnEntry->id}"]);
});

it('defines the event movement exporter and completion summary', function (): void {
    $columns = collect(ContainerMovementExporter::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();
    $export = new Export([
        'successful_rows' => 4,
        'total_rows' => 5,
    ]);

    expect(ContainerMovementExporter::getModel())->toBe(ContainerMovement::class)
        ->and($columns)->toBe([
            'container.serial',
            'container.name',
            'movement_date',
            'customer.name',
            'document_type',
            'document_code',
            'cross_return',
        ])->and(ContainerMovementExporter::getCompletedNotificationBody($export))
        ->toBe('Container movement export finished: 4 successful rows, 1 failed rows.');
});

it('requires view access to the owner container', function (): void {
    $user = User::query()->create([
        'name' => 'Movement Authorized User',
        'email' => 'movement-authorized@example.com',
    ]);
    $container = Container::factory()->create();

    actingAs($user);

    expect(MovementsRelationManager::canViewForRecord($container, ViewContainer::class))->toBeFalse();

    Gate::before(static fn (mixed $user, string $ability): bool => true);

    expect(MovementsRelationManager::canViewForRecord($container, ViewContainer::class))->toBeTrue();
});
