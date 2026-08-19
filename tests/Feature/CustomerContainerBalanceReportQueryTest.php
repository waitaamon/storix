<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\CustomerContainerBalanceQuery;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\CustomerCategory;
use Storix\Tests\Fixtures\Models\User;

function balanceReportCustomer(string $name, ?int $categoryId = null): Customer
{
    return Customer::query()->create([
        'name' => $name,
        'account_category_id' => $categoryId,
    ]);
}

function balanceReportUser(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::slug($name).'-'.Str::ulid().'@example.com',
    ]);
}

function balanceReportDispatch(
    Customer $customer,
    string $state = 'approved',
    int $quantity = 1,
): Dispatch {
    $user = balanceReportUser($customer->getAttribute('name').' Dispatcher');

    $dispatch = Dispatch::query()->create([
        'customer_id' => $customer->getKey(),
        'delivery_note_id' => null,
        'dispatched_by' => $user->getKey(),
        'quantity' => $quantity,
        'dispatched_at' => now(),
        'state' => $state,
        'approved_by' => $state === 'approved' ? $user->getKey() : null,
        'approved_at' => $state === 'approved' ? now() : null,
    ]);

    for ($index = 0; $index < $quantity; $index++) {
        DispatchEntry::factory()->create([
            'dispatch_id' => $dispatch->getKey(),
            'container_id' => Container::factory()->create([
                'name' => 'Balance Report '.Str::ulid(),
                'serial' => 'BAL-'.Str::ulid(),
            ])->getKey(),
        ]);
    }

    return $dispatch;
}

function balanceReportReturn(
    Customer $customer,
    ReturnCondition $condition = ReturnCondition::Good,
    string $state = 'approved',
    ?DispatchEntry $dispatchEntry = null,
    bool $crossReturn = false,
): ContainerReturn {
    $preparer = balanceReportUser($customer->getAttribute('name').' Return Preparer');
    $approver = balanceReportUser($customer->getAttribute('name').' Return Approver');

    $containerReturn = ContainerReturn::query()->create([
        'customer_id' => $customer->getKey(),
        'user_id' => $preparer->getKey(),
        'approved_by' => $state === 'approved' ? $approver->getKey() : null,
        'approved_at' => $state === 'approved' ? now() : null,
        'state' => $state,
        'transaction_date' => today(),
    ]);

    ContainerReturnEntry::factory()->create([
        'container_return_id' => $containerReturn->getKey(),
        'container_id' => $dispatchEntry instanceof DispatchEntry
            ? $dispatchEntry->getAttribute('container_id')
            : Container::factory()->create([
                'name' => 'Balance Return '.Str::ulid(),
                'serial' => 'RET-'.Str::ulid(),
            ])->getKey(),
        'dispatch_entry_id' => $dispatchEntry?->getKey(),
        'return_condition' => $condition,
        'cross_return' => $crossReturn,
    ]);

    return $containerReturn;
}

it('reports approved customer-attributed quantities and keeps active zero and negative balances', function (): void {
    $sourceCustomer = balanceReportCustomer('A Source Customer');
    $returningCustomer = balanceReportCustomer('B Returning Customer');
    $settledCustomer = balanceReportCustomer('C Settled Customer');
    balanceReportCustomer('D No Activity Customer');

    $sourceDispatch = balanceReportDispatch($sourceCustomer, quantity: 2);
    $sourceEntries = DispatchEntry::query()
        ->where('dispatch_id', $sourceDispatch->getKey())
        ->orderBy('id')
        ->get();

    balanceReportReturn(
        $returningCustomer,
        ReturnCondition::Good,
        dispatchEntry: $sourceEntries->firstOrFail(),
        crossReturn: true,
    );
    balanceReportReturn(
        $returningCustomer,
        ReturnCondition::Lost,
        dispatchEntry: $sourceEntries->last(),
        crossReturn: true,
    );

    $settledDispatch = balanceReportDispatch($settledCustomer);
    balanceReportReturn(
        $settledCustomer,
        ReturnCondition::Damaged,
        dispatchEntry: DispatchEntry::query()
            ->where('dispatch_id', $settledDispatch->getKey())
            ->sole(),
    );

    $rows = app(CustomerContainerBalanceQuery::class)
        ->forReport()
        ->orderBy('customers.name')
        ->get()
        ->keyBy('name');

    $sourceRow = $rows->get('A Source Customer');
    $returningRow = $rows->get('B Returning Customer');
    $settledRow = $rows->get('C Settled Customer');

    if (! $sourceRow instanceof Model
        || ! $returningRow instanceof Model
        || ! $settledRow instanceof Model) {
        throw new LogicException('The expected customer balance rows were not returned.');
    }

    expect($rows)->toHaveCount(3)
        ->and($rows->keys()->all())->toBe([
            'A Source Customer',
            'B Returning Customer',
            'C Settled Customer',
        ])
        ->and((int) $sourceRow->getAttribute('dispatched'))->toBe(2)
        ->and((int) $sourceRow->getAttribute('returned'))->toBe(0)
        ->and((int) $sourceRow->getAttribute('lost'))->toBe(0)
        ->and((int) $sourceRow->getAttribute('balance'))->toBe(2)
        ->and((int) $returningRow->getAttribute('dispatched'))->toBe(0)
        ->and((int) $returningRow->getAttribute('returned'))->toBe(1)
        ->and((int) $returningRow->getAttribute('lost'))->toBe(1)
        ->and((int) $returningRow->getAttribute('balance'))->toBe(-2)
        ->and((int) $settledRow->getAttribute('balance'))->toBe(0);
});

it('counts a returned container again when the same serial is redispatched', function (): void {
    $customer = balanceReportCustomer('Redispatch Customer');
    $firstDispatch = balanceReportDispatch($customer);
    $firstEntry = DispatchEntry::query()
        ->where('dispatch_id', $firstDispatch->getKey())
        ->sole();

    balanceReportReturn(
        $customer,
        ReturnCondition::Good,
        dispatchEntry: $firstEntry,
    );

    $dispatcher = balanceReportUser('Redispatch Dispatcher');
    $redispatch = Dispatch::query()->create([
        'customer_id' => $customer->getKey(),
        'delivery_note_id' => null,
        'dispatched_by' => $dispatcher->getKey(),
        'quantity' => 1,
        'dispatched_at' => now(),
        'state' => 'approved',
        'approved_by' => $dispatcher->getKey(),
        'approved_at' => now(),
    ]);
    DispatchEntry::factory()->create([
        'dispatch_id' => $redispatch->getKey(),
        'container_id' => $firstEntry->getAttribute('container_id'),
    ]);

    $row = app(CustomerContainerBalanceQuery::class)->forReport()->sole();

    expect((int) $row->getAttribute('dispatched'))->toBe(2)
        ->and((int) $row->getAttribute('returned'))->toBe(1)
        ->and((int) $row->getAttribute('lost'))->toBe(0)
        ->and((int) $row->getAttribute('balance'))->toBe(1);
});

it('excludes unposted deleted and soft-deleted activity', function (): void {
    $customer = balanceReportCustomer('Posting Controls Customer');

    balanceReportDispatch($customer);
    balanceReportDispatch($customer, state: 'draft', quantity: 2);
    balanceReportDispatch($customer, state: 'voided', quantity: 2);

    $deletedDispatch = balanceReportDispatch($customer, quantity: 2);
    $deletedDispatch->delete();

    $dispatchWithDeletedEntry = balanceReportDispatch($customer, quantity: 2);
    $dispatchWithDeletedEntry->entries()->firstOrFail()->delete();

    balanceReportReturn($customer, ReturnCondition::Good);
    balanceReportReturn($customer, ReturnCondition::Damaged, state: 'submitted');
    balanceReportReturn($customer, ReturnCondition::Lost, state: 'draft');

    $deletedReturn = balanceReportReturn($customer, ReturnCondition::Lost);
    $deletedReturn->delete();

    $row = app(CustomerContainerBalanceQuery::class)->forReport()->sole();

    expect((int) $row->getAttribute('dispatched'))->toBe(2)
        ->and((int) $row->getAttribute('returned'))->toBe(1)
        ->and((int) $row->getAttribute('lost'))->toBe(0)
        ->and((int) $row->getAttribute('balance'))->toBe(1);
});

it('applies the configured customer query modifier to report rows', function (): void {
    $receivable = balanceReportCustomer('Included Receivable');
    $otherCategory = CustomerCategory::query()->create([
        'name' => 'Other Accounts',
        'slug' => 'other-accounts',
    ]);
    $excluded = balanceReportCustomer('Excluded Account', $otherCategory->getKey());

    balanceReportDispatch($receivable);
    balanceReportDispatch($excluded);

    expect(app(CustomerContainerBalanceQuery::class)->forReport()->pluck('name')->all())
        ->toBe(['Included Receivable']);
});

it('uses the aggregate query for the existing single-customer balance service', function (): void {
    $customer = balanceReportCustomer('Single Balance Customer');
    balanceReportDispatch($customer, quantity: 3);
    balanceReportReturn($customer, ReturnCondition::Good);
    balanceReportReturn($customer, ReturnCondition::Damaged);
    balanceReportReturn($customer, ReturnCondition::Lost);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $balance = app(CustomerContainerBalanceQuery::class)->forCustomer($customer->getKey());
    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($balance->dispatched)->toBe(3)
        ->and($balance->returned)->toBe(2)
        ->and($balance->lost)->toBe(1)
        ->and($balance->outstanding)->toBe(0)
        ->and($queryCount)->toBe(1);
});

it('keeps report query volume constant as customer activity grows', function (): void {
    $smallCustomer = balanceReportCustomer('Small Volume Customer');
    balanceReportDispatch($smallCustomer);

    for ($index = 0; $index < 20; $index++) {
        $customer = balanceReportCustomer("Large Volume Customer {$index}");
        balanceReportDispatch($customer, quantity: 2);
        balanceReportReturn($customer, ReturnCondition::Good);
    }

    Model::preventLazyLoading();
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $rows = app(CustomerContainerBalanceQuery::class)->forReport()->get();
        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        Model::preventLazyLoading(false);
    }

    expect($rows)->toHaveCount(21)
        ->and($queryCount)->toBe(1);
});

it('installs the customer balance reporting indexes on sqlite', function (): void {
    expect(Schema::hasIndex(
        TableNames::dispatches(),
        'storix_d_state_deleted_customer_idx',
    ))->toBeTrue()
        ->and(Schema::hasIndex(
            TableNames::dispatchEntries(),
            'storix_de_dispatch_deleted_idx',
        ))->toBeTrue()
        ->and(Schema::hasIndex(
            TableNames::containerReturns(),
            'storix_cr_state_deleted_customer_idx',
        ))->toBeTrue()
        ->and(Schema::hasIndex(
            TableNames::containerReturnEntries(),
            'storix_cre_return_idx',
        ))->toBeTrue();
});
