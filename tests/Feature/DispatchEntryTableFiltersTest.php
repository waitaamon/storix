<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Storix\Filament\Resources\DispatchEntriesResources\Pages\ListDispatchEntries;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\DeliveryNote;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

function dispatchForEntryFilters(
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

function dispatchEntryForFilters(Dispatch $dispatch): DispatchEntry
{
    return DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => Container::factory()->create()->id,
    ]);
}

/** @return Testable<ListDispatchEntries> */
function dispatchEntryFiltersPage(User $user): Testable
{
    actingAs($user);
    Filament::setCurrentPanel('test');

    return Livewire::test(ListDispatchEntries::class);
}

beforeEach(function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);
});

it('filters dispatch entries by their dispatch customer', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'entry-customer-filter@example.com']);
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
    $selectedEntry = dispatchEntryForFilters(
        dispatchForEntryFilters($user, $selectedDeliveryNote, '2026-03-10 09:00:00'),
    );
    $otherEntry = dispatchEntryForFilters(
        dispatchForEntryFilters($user, $otherDeliveryNote, '2026-03-10 10:00:00'),
    );

    dispatchEntryFiltersPage($user)
        ->filterTable('customer', $selectedCustomer)
        ->assertCanSeeTableRecords([$selectedEntry])
        ->assertCanNotSeeTableRecords([$otherEntry]);
});

it('filters dispatch entries by an inclusive dispatch approval date range', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'entry-dispatch-date-filter@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Dispatch date delivery']);
    $before = dispatchEntryForFilters(dispatchForEntryFilters($user, $deliveryNote, '2026-03-09 23:59:59'));
    $fromBoundary = dispatchEntryForFilters(dispatchForEntryFilters($user, $deliveryNote, '2026-03-10 00:00:00'));
    $untilBoundary = dispatchEntryForFilters(dispatchForEntryFilters($user, $deliveryNote, '2026-03-20 23:59:59'));
    $after = dispatchEntryForFilters(dispatchForEntryFilters($user, $deliveryNote, '2026-03-21 00:00:00'));
    $unapproved = dispatchEntryForFilters(dispatchForEntryFilters($user, $deliveryNote, null));

    dispatchEntryFiltersPage($user)
        ->filterTable('approved_at', [
            'from' => '2026-03-10',
            'until' => '2026-03-20',
        ])
        ->assertCanSeeTableRecords([$fromBoundary, $untilBoundary])
        ->assertCanNotSeeTableRecords([$before, $after, $unapproved]);
});

it('combines dispatch entry filters using intersection semantics', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'entry-combined-filter@example.com']);
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
    $selectedDispatch = dispatchForEntryFilters($user, $selectedDeliveryNote, '2026-03-15 09:00:00');
    $matchingEntry = dispatchEntryForFilters($selectedDispatch);
    $wrongCustomer = dispatchEntryForFilters(
        dispatchForEntryFilters($user, $otherDeliveryNote, '2026-03-15 10:00:00'),
    );
    $wrongDate = dispatchEntryForFilters(
        dispatchForEntryFilters($user, $selectedDeliveryNote, '2026-03-21 10:00:00'),
    );

    dispatchEntryFiltersPage($user)
        ->filterTable('customer', $selectedCustomer)
        ->filterTable('approved_at', ['from' => '2026-03-10', 'until' => '2026-03-20'])
        ->assertCanSeeTableRecords([$matchingEntry])
        ->assertCanNotSeeTableRecords([$wrongCustomer, $wrongDate]);
});

it('excludes soft-deleted dispatch entries without exposing a deleted-record filter', function (): void {
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'deleted-entry@example.com']);
    $deliveryNote = DeliveryNote::query()->create(['name' => 'Deleted entry delivery']);
    $dispatch = dispatchForEntryFilters($user, $deliveryNote, '2026-03-10 09:00:00');
    $activeEntry = dispatchEntryForFilters($dispatch);
    $deletedEntry = dispatchEntryForFilters($dispatch);

    $deletedEntry->delete();

    dispatchEntryFiltersPage($user)
        ->assertCanSeeTableRecords([$activeEntry])
        ->assertCanNotSeeTableRecords([$deletedEntry]);
});
