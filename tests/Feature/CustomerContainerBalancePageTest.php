<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Storix\Filament\Pages\CustomerContainerBalances;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Permissions\StorixPermissions;
use Storix\Support\CustomerContainerBalanceQuery;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\CustomerWithFinancialBalance;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function balancePageUser(string $name, string $email): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $email,
    ]);
}

function balancePageDispatch(Customer $customer, int $quantity): Dispatch
{
    $dispatcher = balancePageUser(
        "{$customer->getAttribute('name')} Dispatcher",
        Str::ulid().'@example.com',
    );

    $dispatch = Dispatch::query()->create([
        'customer_id' => $customer->getKey(),
        'delivery_note_id' => null,
        'dispatched_by' => $dispatcher->getKey(),
        'quantity' => $quantity,
        'dispatched_at' => now(),
        'state' => 'approved',
        'approved_by' => $dispatcher->getKey(),
        'approved_at' => now(),
    ]);

    for ($index = 0; $index < $quantity; $index++) {
        DispatchEntry::factory()->create([
            'dispatch_id' => $dispatch->getKey(),
            'container_id' => Container::factory()->create([
                'name' => 'Balance Page '.Str::ulid(),
                'serial' => 'PAGE-'.Str::ulid(),
            ])->getKey(),
        ]);
    }

    return $dispatch;
}

beforeEach(function (): void {
    Filament::setCurrentPanel('test');
});

it('registers the balance report page and supports a configurable navigation label', function (): void {
    expect(Filament::getPanel('test')->getPages())->toContain(CustomerContainerBalances::class)
        ->and(CustomerContainerBalances::getSlug(Filament::getPanel('test')))
        ->toBe('customer-container-balances')
        ->and(CustomerContainerBalances::getNavigationGroup())->toBe('Storix')
        ->and(CustomerContainerBalances::getNavigationLabel())->toBe('Customer Container Balances');

    Config::set(
        'storix.navigation.customer_container_balances_label',
        'Reusable Asset Balances',
    );

    expect(CustomerContainerBalances::getNavigationLabel())->toBe('Reusable Asset Balances');
});

it('requires the dedicated permission for navigation and direct page access', function (): void {
    Gate::define(
        StorixPermissions::VIEW_ANY_CUSTOMER_CONTAINER_BALANCES,
        static fn (User $user): bool => $user->getAttribute('email') === 'report-authorized@example.com',
    );

    $unauthorized = balancePageUser('Unauthorized Report User', 'report-denied@example.com');
    $authorized = balancePageUser('Authorized Report User', 'report-authorized@example.com');

    actingAs($unauthorized);

    expect(CustomerContainerBalances::canAccess())->toBeFalse();

    get(CustomerContainerBalances::getUrl(panel: 'test'))->assertForbidden();
    Livewire::test(CustomerContainerBalances::class)->assertForbidden();

    actingAs($authorized);

    expect(CustomerContainerBalances::canAccess())->toBeTrue();

    get(CustomerContainerBalances::getUrl(panel: 'test'))->assertOk();
    Livewire::test(CustomerContainerBalances::class)->assertOk();
});

it('renders searchable and sortable aggregate balance columns', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);

    $user = balancePageUser('Balance Report Viewer', 'balance-report-viewer@example.com');
    $alpha = Customer::query()->create(['name' => 'Alpha Balance Customer']);
    $beta = Customer::query()->create(['name' => 'Beta Balance Customer']);

    balancePageDispatch($alpha, 2);
    balancePageDispatch($beta, 1);

    actingAs($user);

    $page = Livewire::test(CustomerContainerBalances::class);

    $page->assertOk();

    $page
        ->assertCanSeeTableRecords([$alpha, $beta])
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('dispatched')
        ->assertTableColumnExists('returned')
        ->assertTableColumnExists('lost')
        ->assertTableColumnExists('balance')
        ->assertTableColumnStateSet('dispatched', 2, $alpha)
        ->assertTableColumnStateSet('returned', 0, $alpha)
        ->assertTableColumnStateSet('lost', 0, $alpha)
        ->assertTableColumnStateSet('balance', 2, $alpha);

    $page
        ->searchTable('Alpha')
        ->assertCanSeeTableRecords([$alpha])
        ->assertCanNotSeeTableRecords([$beta]);

    $page
        ->searchTable()
        ->sortTable('dispatched', 'desc')
        ->assertCanSeeTableRecords([$alpha, $beta], inOrder: true);
});

it('renders the projected container balance when the customer model has a financial balance accessor', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);

    $user = balancePageUser('Accessor Collision Viewer', 'accessor-collision@example.com');
    $customer = Customer::query()->create(['name' => 'Accessor Collision Customer']);
    balancePageDispatch($customer, 2);

    Config::set('storix.models.customer', CustomerWithFinancialBalance::class);
    actingAs($user);

    $reportCustomer = app(CustomerContainerBalanceQuery::class)->forReport()->sole();

    expect($reportCustomer->getAttribute('balance'))->toBe(78_300.0)
        ->and((int) $reportCustomer->getRawOriginal('balance'))->toBe(2);

    $page = Livewire::test(CustomerContainerBalances::class);

    $page->assertOk();
    $page->assertTableColumnStateSet('balance', 2, $reportCustomer);
});

it('exposes the report permission through the permission registry', function (): void {
    expect(StorixPermissions::reportPermissions())->toBe([
        StorixPermissions::VIEW_ANY_CUSTOMER_CONTAINER_BALANCES,
    ])->and(StorixPermissions::all())
        ->toContain(StorixPermissions::VIEW_ANY_CUSTOMER_CONTAINER_BALANCES);
});
