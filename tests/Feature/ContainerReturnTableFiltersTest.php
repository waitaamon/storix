<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\ContainerReturnEntriesResources\Pages\ListContainerReturnEntries;
use Storix\Filament\Resources\ContainerReturnResources\Pages\ListContainerReturns;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);
    Filament::setCurrentPanel('test');
});

it('filters return documents by customer, state, and inclusive transaction dates', function (): void {
    $user = User::query()->create(['name' => 'Filter User', 'email' => 'return-filter@example.com']);
    $selectedCustomer = Customer::query()->create(['name' => 'Selected Customer']);
    $otherCustomer = Customer::query()->create(['name' => 'Other Customer']);
    $selected = ContainerReturn::factory()->submitted()->create([
        'customer_id' => $selectedCustomer->id,
        'user_id' => $user->id,
        'transaction_date' => '2026-07-10',
    ]);
    $wrongState = ContainerReturn::factory()->draft()->create([
        'customer_id' => $selectedCustomer->id,
        'user_id' => $user->id,
        'transaction_date' => '2026-07-10',
    ]);
    $wrongCustomer = ContainerReturn::factory()->submitted()->create([
        'customer_id' => $otherCustomer->id,
        'user_id' => $user->id,
        'transaction_date' => '2026-07-10',
    ]);
    $wrongDate = ContainerReturn::factory()->submitted()->create([
        'customer_id' => $selectedCustomer->id,
        'user_id' => $user->id,
        'transaction_date' => '2026-07-21',
    ]);

    actingAs($user);

    Livewire::test(ListContainerReturns::class)
        ->filterTable('customer', $selectedCustomer)
        ->filterTable('state', 'submitted')
        ->filterTable('transaction_date', [
            'from' => '2026-07-01',
            'until' => '2026-07-20',
        ])
        ->assertCanSeeTableRecords([$selected])
        ->assertCanNotSeeTableRecords([$wrongState, $wrongCustomer, $wrongDate]);
});

it('filters return entries by condition and cross-return control flag', function (): void {
    $user = User::query()->create(['name' => 'Entry Filter', 'email' => 'entry-filter@example.com']);
    $containerReturn = ContainerReturn::factory()->approved()->create(['user_id' => $user->id]);
    $customer = Customer::query()->findOrFail($containerReturn->customer_id);
    $crossDamaged = ContainerReturnEntry::factory()
        ->damaged()
        ->crossReturn()
        ->create(['container_return_id' => $containerReturn->id]);
    $sameCustomer = ContainerReturnEntry::factory()
        ->create(['container_return_id' => $containerReturn->id]);

    actingAs($user);

    Livewire::test(ListContainerReturnEntries::class)
        ->filterTable('customer', $customer)
        ->filterTable('state', 'approved')
        ->filterTable('transaction_date', [
            'from' => $containerReturn->transaction_date->toDateString(),
            'until' => $containerReturn->transaction_date->toDateString(),
        ])
        ->filterTable('return_condition', ReturnCondition::Damaged->value)
        ->filterTable('cross_return', '1')
        ->assertCanSeeTableRecords([$crossDamaged])
        ->assertCanNotSeeTableRecords([$sameCustomer]);
});
