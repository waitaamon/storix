<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Storix\Enums\ReturnCondition;
use Storix\Events\ContainerDamaged;
use Storix\Events\ContainerLost;
use Storix\Events\ContainerReturned;
use Storix\Filament\Resources\DispatchEntriesResources\Actions\ReceiveSelectedContainersBulkAction;
use Storix\Filament\Resources\DispatchEntriesResources\Pages\ListDispatchEntries;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

function authorizeBulkContainerReturns(bool $canReceive = true): void
{
    Gate::before(static fn (mixed $user, string $ability): ?bool => match ($ability) {
        'manage.dispatch-entries' => false,
        'receive.dispatch-entries' => $canReceive,
        'receive' => null,
        default => true,
    });
}

/**
 * @return Collection<int, DispatchEntry>
 */
function bulkReturnEntries(
    User $user,
    int $count,
    string $dispatchedAt = '2026-03-01 09:00:00',
): Collection {
    $deliveryNote = DeliveryNote::query()->create(['name' => "Bulk return {$dispatchedAt}"]);
    $dispatch = Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'dispatched_at' => $dispatchedAt,
        'quantity' => $count,
        'state' => 'approved',
    ]);

    foreach (Container::factory()->count($count)->create() as $container) {
        DispatchEntry::query()->create([
            'dispatch_id' => $dispatch->id,
            'container_id' => $container->id,
        ]);
    }

    return DispatchEntry::query()
        ->where('dispatch_id', $dispatch->id)
        ->get();
}

function bulkReturnPage(User $user): ListDispatchEntries
{
    actingAs($user);
    Filament::setCurrentPanel('test');

    $page = app(ListDispatchEntries::class);
    $page->mount();
    $page->mountInteractsWithTable();
    $page->bootedInteractsWithTable();

    return $page;
}

/**
 * @param  iterable<array-key, DispatchEntry>  $entries
 * @param  array<string, mixed>  $data
 */
function executeBulkReturn(ListDispatchEntries $page, iterable $entries, array $data): void
{
    $keys = [];

    foreach ($entries as $entry) {
        $keys[] = (string) $entry->getKey();
    }

    $page->mountTableBulkAction('receiveSelectedContainers', $keys);
    $schema = $page->getMountedTableBulkActionForm();

    if (! $schema instanceof Schema) {
        throw new LogicException('The bulk receive schema was not mounted.');
    }

    $schema->fill($data);
    $page->callMountedTableBulkAction();
}

it('uses configured plural container labels and shared return defaults', function (): void {
    authorizeBulkContainerReturns();
    config()->set('storix.labels.container', 'gas cylinder');

    $user = User::query()->create(['name' => 'Receiver', 'email' => 'bulk-labels@example.com']);
    $entry = bulkReturnEntries($user, 1)->sole();
    $page = bulkReturnPage($user);
    $action = $page->getTable()->getBulkAction('receiveSelectedContainers');

    expect($action)->toBeInstanceOf(ReceiveSelectedContainersBulkAction::class);

    if (! $action instanceof ReceiveSelectedContainersBulkAction) {
        throw new LogicException('The bulk receive action is not configured.');
    }

    expect($action->getLabel())->toBe('Return Selected Gas Cylinders');

    $page->mountTableBulkAction('receiveSelectedContainers', [(string) $entry->getKey()]);
    $schema = $page->getMountedTableBulkActionForm();
    $action = $page->getMountedTableBulkAction();

    expect($action)->toBeInstanceOf(ReceiveSelectedContainersBulkAction::class);

    if (! $action instanceof ReceiveSelectedContainersBulkAction || ! $schema) {
        throw new LogicException('The bulk receive action or schema was not mounted.');
    }

    $state = $schema->getState();

    expect($action->getModalHeading())->toBe('Receive Gas Cylinders')
        ->and(CarbonImmutable::parse($state['return_date'])->isToday())->toBeTrue()
        ->and($state['return_condition'])->toBe(ReturnCondition::Good);
});

it('receives multiple selected containers through the lifecycle action', function (): void {
    authorizeBulkContainerReturns();
    Event::fake([ContainerReturned::class, ContainerDamaged::class]);

    $user = User::query()->create(['name' => 'Receiver', 'email' => 'bulk-success@example.com']);
    $entries = bulkReturnEntries($user, 3);

    executeBulkReturn(bulkReturnPage($user), $entries, [
        'return_date' => '2026-03-20',
        'return_condition' => ReturnCondition::Damaged->value,
        'return_note' => 'Inspected in the return bay',
    ]);

    $returnedEntries = DispatchEntry::query()->whereKey($entries->modelKeys())->get();

    expect($returnedEntries)->toHaveCount(3)
        ->and($returnedEntries->every(fn (DispatchEntry $entry): bool => $entry->received_by === $user->id))->toBeTrue()
        ->and($returnedEntries->every(fn (DispatchEntry $entry): bool => $entry->return_date?->isSameDay('2026-03-20') ?? false))->toBeTrue()
        ->and($returnedEntries->every(fn (DispatchEntry $entry): bool => $entry->return_condition === ReturnCondition::Damaged))->toBeTrue()
        ->and($returnedEntries->every(fn (DispatchEntry $entry): bool => $entry->return_note === 'Inspected in the return bay'))->toBeTrue();

    Event::assertDispatchedTimes(ContainerReturned::class, 3);
    Event::assertDispatchedTimes(ContainerDamaged::class, 3);
});

it('deactivates selected containers recorded as lost', function (): void {
    authorizeBulkContainerReturns();
    Event::fake([ContainerLost::class]);

    $user = User::query()->create(['name' => 'Receiver', 'email' => 'bulk-lost@example.com']);
    $entries = bulkReturnEntries($user, 2);
    $containerIds = $entries->pluck('container_id');

    executeBulkReturn(bulkReturnPage($user), $entries, [
        'return_date' => '2026-03-20',
        'return_condition' => ReturnCondition::Lost->value,
        'return_note' => 'Confirmed lost after reconciliation',
    ]);

    expect(Container::query()->whereKey($containerIds)->where('is_active', false)->count())->toBe(2);
    Event::assertDispatchedTimes(ContainerLost::class, 2);
});

it('processes authorized outstanding entries and leaves returned selections unchanged', function (): void {
    authorizeBulkContainerReturns();

    $user = User::query()->create(['name' => 'Receiver', 'email' => 'bulk-policy@example.com']);
    $entries = bulkReturnEntries($user, 2);
    $outstanding = $entries->firstOrFail();
    $alreadyReturned = $entries->last();

    if (! $alreadyReturned instanceof DispatchEntry) {
        throw new LogicException('The previously returned entry was not created.');
    }

    $alreadyReturned->update([
        'received_by' => $user->id,
        'return_date' => '2026-03-05',
        'return_condition' => ReturnCondition::Good,
        'return_note' => 'Original receipt',
    ]);

    executeBulkReturn(bulkReturnPage($user), $entries, [
        'return_date' => '2026-03-20',
        'return_condition' => ReturnCondition::Damaged->value,
        'return_note' => 'New bulk receipt',
    ]);

    expect($outstanding->refresh()->return_condition)->toBe(ReturnCondition::Damaged)
        ->and($alreadyReturned->refresh()->return_date?->isSameDay('2026-03-05'))->toBeTrue()
        ->and($alreadyReturned->return_condition)->toBe(ReturnCondition::Good)
        ->and($alreadyReturned->return_note)->toBe('Original receipt');
});

it('reports domain failures while preserving successful returns', function (): void {
    authorizeBulkContainerReturns();

    $user = User::query()->create(['name' => 'Receiver', 'email' => 'bulk-partial@example.com']);
    $validEntry = bulkReturnEntries($user, 1, '2026-03-01 09:00:00')->sole();
    $invalidEntry = bulkReturnEntries($user, 1, '2026-03-15 09:00:00')->sole();

    session()->forget('filament.notifications');
    executeBulkReturn(bulkReturnPage($user), [$validEntry, $invalidEntry], [
        'return_date' => '2026-03-10',
        'return_condition' => ReturnCondition::Good->value,
        'return_note' => 'Partial receipt',
    ]);

    expect($validEntry->refresh()->return_condition)->toBe(ReturnCondition::Good)
        ->and($invalidEntry->refresh()->return_date)->toBeNull()
        ->and(session('filament.notifications', []))->not->toBeEmpty();
});

it('hides the bulk receive action without receive permission', function (): void {
    authorizeBulkContainerReturns(canReceive: false);

    $user = User::query()->create(['name' => 'Viewer', 'email' => 'bulk-unauthorized@example.com']);
    bulkReturnEntries($user, 1);

    $action = bulkReturnPage($user)->getTable()->getBulkAction('receiveSelectedContainers');

    expect($action)->toBeInstanceOf(ReceiveSelectedContainersBulkAction::class)
        ->and($action?->isVisible())->toBeFalse();
});
