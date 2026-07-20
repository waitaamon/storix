<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\DispatchResources\Pages\ListDispatches;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

function dispatchForFilters(
    User $user,
    DeliveryNote $deliveryNote,
    string $dispatchedAt,
): Dispatch {
    return Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'dispatched_at' => $dispatchedAt,
        'quantity' => 1,
    ]);
}

/** @return Testable<ListDispatches> */
function dispatchFiltersPage(User $user): Testable
{
    actingAs($user);
    Filament::setCurrentPanel('test');

    return Livewire::test(ListDispatches::class);
}

beforeEach(function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);
});

it('filters dispatches by customer', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'customer-filter@example.com']);
    $selectedCustomer = Customer::query()->create(['name' => 'Selected Customer']);
    $otherCustomer = Customer::query()->create(['name' => 'Other Customer']);
    $selectedDeliveryNote = DeliveryNote::query()->create([
        'name' => 'Selected delivery',
        'customer_id' => $selectedCustomer->id,
    ]);
    $otherDeliveryNote = DeliveryNote::query()->create([
        'name' => 'Other delivery',
        'customer_id' => $otherCustomer->id,
    ]);
    $selectedDispatch = dispatchForFilters($user, $selectedDeliveryNote, '2026-03-10 09:00:00');
    $otherDispatch = dispatchForFilters($user, $otherDeliveryNote, '2026-03-10 10:00:00');

    dispatchFiltersPage($user)
        ->filterTable('customer', $selectedCustomer)
        ->assertCanSeeTableRecords([$selectedDispatch])
        ->assertCanNotSeeTableRecords([$otherDispatch]);
});

it('filters dispatches by an inclusive dispatched date range', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'date-filter@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Date range delivery']);
    $before = dispatchForFilters($user, $deliveryNote, '2026-03-09 23:59:59');
    $fromBoundary = dispatchForFilters($user, $deliveryNote, '2026-03-10 00:00:00');
    $untilBoundary = dispatchForFilters($user, $deliveryNote, '2026-03-20 23:59:59');
    $after = dispatchForFilters($user, $deliveryNote, '2026-03-21 00:00:00');

    dispatchFiltersPage($user)
        ->filterTable('dispatched_at', [
            'from' => '2026-03-10',
            'until' => '2026-03-20',
        ])
        ->assertCanSeeTableRecords([$fromBoundary, $untilBoundary])
        ->assertCanNotSeeTableRecords([$before, $after]);
});

it('filters dispatches by a related container return condition', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'condition-filter@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Condition delivery']);
    $damagedDispatch = dispatchForFilters($user, $deliveryNote, '2026-03-10 09:00:00');
    $goodDispatch = dispatchForFilters($user, $deliveryNote, '2026-03-10 10:00:00');

    DispatchEntry::query()->create([
        'dispatch_id' => $damagedDispatch->id,
        'container_id' => Container::factory()->create()->id,
        'return_date' => '2026-03-12',
        'return_condition' => ReturnCondition::Damaged,
    ]);
    DispatchEntry::query()->create([
        'dispatch_id' => $goodDispatch->id,
        'container_id' => Container::factory()->create()->id,
        'return_date' => '2026-03-12',
        'return_condition' => ReturnCondition::Good,
    ]);

    dispatchFiltersPage($user)
        ->filterTable('return_condition', ReturnCondition::Damaged)
        ->assertCanSeeTableRecords([$damagedDispatch])
        ->assertCanNotSeeTableRecords([$goodDispatch]);
});

it('filters dispatches when a single return falls within an inclusive date range', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'return-date-filter@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Return date delivery']);
    $matchingDispatch = dispatchForFilters($user, $deliveryNote, '2026-03-01 09:00:00');
    $splitRangeDispatch = dispatchForFilters($user, $deliveryNote, '2026-03-01 10:00:00');

    DispatchEntry::query()->create([
        'dispatch_id' => $matchingDispatch->id,
        'container_id' => Container::factory()->create()->id,
        'return_date' => '2026-03-20',
        'return_condition' => ReturnCondition::Good,
    ]);
    DispatchEntry::query()->create([
        'dispatch_id' => $splitRangeDispatch->id,
        'container_id' => Container::factory()->create()->id,
        'return_date' => '2026-03-09',
        'return_condition' => ReturnCondition::Good,
    ]);
    DispatchEntry::query()->create([
        'dispatch_id' => $splitRangeDispatch->id,
        'container_id' => Container::factory()->create()->id,
        'return_date' => '2026-03-21',
        'return_condition' => ReturnCondition::Good,
    ]);

    dispatchFiltersPage($user)
        ->filterTable('return_date', [
            'from' => '2026-03-10',
            'until' => '2026-03-20',
        ])
        ->assertCanSeeTableRecords([$matchingDispatch])
        ->assertCanNotSeeTableRecords([$splitRangeDispatch]);
});
