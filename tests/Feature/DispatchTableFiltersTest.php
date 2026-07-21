<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Storix\Filament\Resources\DispatchResources\Pages\ListDispatches;
use Storix\Models\Dispatch;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

function dispatchForFilters(
    User $user,
    DeliveryNote $deliveryNote,
    ?string $approvedAt,
): Dispatch {
    return Dispatch::query()->create([
        'dispatched_by' => $user->id,
        'delivery_note_id' => $deliveryNote->id,
        'dispatched_at' => '2026-01-15 09:00:00',
        'approved_at' => $approvedAt,
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

it('filters dispatches by an inclusive approval date range used as the dispatch date', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'date-filter@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Date range delivery']);
    $before = dispatchForFilters($user, $deliveryNote, '2026-03-09 23:59:59');
    $fromBoundary = dispatchForFilters($user, $deliveryNote, '2026-03-10 00:00:00');
    $untilBoundary = dispatchForFilters($user, $deliveryNote, '2026-03-20 23:59:59');
    $after = dispatchForFilters($user, $deliveryNote, '2026-03-21 00:00:00');
    $unapproved = dispatchForFilters($user, $deliveryNote, null);

    dispatchFiltersPage($user)
        ->filterTable('approved_at', [
            'from' => '2026-03-10',
            'until' => '2026-03-20',
        ])
        ->assertCanSeeTableRecords([$fromBoundary, $untilBoundary])
        ->assertCanNotSeeTableRecords([$before, $after, $unapproved]);
});

it('excludes soft-deleted dispatches without exposing a deleted-record filter', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'deleted-dispatch@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Deleted dispatch delivery']);
    $activeDispatch = dispatchForFilters($user, $deliveryNote, '2026-03-10 09:00:00');
    $deletedDispatch = dispatchForFilters($user, $deliveryNote, '2026-03-10 10:00:00');

    $deletedDispatch->delete();

    dispatchFiltersPage($user)
        ->assertCanSeeTableRecords([$activeDispatch])
        ->assertCanNotSeeTableRecords([$deletedDispatch]);
});
