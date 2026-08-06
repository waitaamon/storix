<?php

declare(strict_types=1);

use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Storix\Enums\ReturnCondition;
use Storix\Events\ContainerDamaged;
use Storix\Events\ContainerLost;
use Storix\Events\ContainerReturnApproved;
use Storix\Events\ContainerReturned;
use Storix\Events\ContainerReturnSubmitted;
use Storix\Filament\Imports\ContainerReturnEntryImporter;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\CustomerContainerBalanceQuery;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

/**
 * @param  array<int, array<string, string>>  $rows
 * @param  array<string, string>  $columnMap
 */
function runContainerReturnImportJob(
    User $user,
    ContainerReturn $containerReturn,
    array $rows,
    array $columnMap = [
        'serial' => 'serial',
        'return_condition' => 'return_condition',
        'note' => 'note',
    ],
): Import {
    $import = Import::query()->create([
        'file_name' => 'container-return-entries.csv',
        'file_path' => 'container-return-entries.csv',
        'importer' => ContainerReturnEntryImporter::class,
        'total_rows' => count($rows),
        'user_id' => $user->id,
    ]);
    $import->setRelation('user', $user);

    $job = new ImportCsv(
        import: $import,
        rows: $rows,
        columnMap: $columnMap,
        options: ['container_return_id' => $containerReturn->id],
    );
    $job->handle();

    return Import::query()->whereKey($import->getKey())->sole();
}

/**
 * @param  list<Container>  $containers
 */
function createApprovedImportDispatch(Customer $customer, User $user, array $containers): Dispatch
{
    $dispatch = Dispatch::factory()->create([
        'customer_id' => $customer->id,
        'dispatched_by' => $user->id,
        'quantity' => count($containers),
        'state' => 'approved',
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    foreach ($containers as $container) {
        DispatchEntry::factory()->create([
            'dispatch_id' => $dispatch->id,
            'container_id' => $container->id,
        ]);
    }

    return $dispatch;
}

beforeEach(function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);
});

it('processes successful and failed CSV rows through the real Filament import job', function (): void {
    $user = User::query()->create([
        'name' => 'Import Job User',
        'email' => 'import-job-user@example.com',
    ]);
    $customer = Customer::query()->create(['name' => 'Import Job Customer']);
    $container = Container::factory()->create();
    createApprovedImportDispatch($customer, $user, [$container]);
    $containerReturn = ContainerReturn::factory()->draft()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
    ]);

    actingAs($user);

    $import = runContainerReturnImportJob($user, $containerReturn, [
        [
            'serial' => $container->serial,
            'return_condition' => ReturnCondition::Good->value,
            'note' => 'Valid row',
        ],
        [
            'serial' => $container->serial,
            'return_condition' => ReturnCondition::Good->value,
            'note' => 'Duplicate row',
        ],
        [
            'serial' => 'MISSING-999',
            'return_condition' => ReturnCondition::Damaged->value,
            'note' => 'Unknown serial',
        ],
    ]);

    $failureMessages = $import->failedRows()->pluck('validation_error')->implode(' ');

    expect($import->processed_rows)->toBe(3)
        ->and($import->successful_rows)->toBe(1)
        ->and($import->getFailedRowsCount())->toBe(2)
        ->and($import->failedRows()->count())->toBe(2)
        ->and($failureMessages)->toContain('has already been added', 'No container found with serial [MISSING-999]')
        ->and($containerReturn->entries()->count())->toBe(1);
});

it('keeps imported return entries unposted until independent approval', function (): void {
    Event::fake([
        ContainerReturned::class,
        ContainerDamaged::class,
        ContainerLost::class,
        ContainerReturnApproved::class,
        ContainerReturnSubmitted::class,
    ]);

    $user = User::query()->create([
        'name' => 'Non Posting Importer',
        'email' => 'non-posting-importer@example.com',
    ]);
    $customer = Customer::query()->create(['name' => 'Non Posting Customer']);
    $containers = Container::factory()->count(3)->create();
    createApprovedImportDispatch($customer, $user, $containers->all());
    $containerReturn = ContainerReturn::factory()->draft()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
    ]);

    actingAs($user);

    $rows = [];

    foreach ([ReturnCondition::Good, ReturnCondition::Damaged, ReturnCondition::Lost] as $index => $condition) {
        $rows[] = [
            'serial' => $containers[$index]->serial,
            'return_condition' => $condition->value,
            'note' => "Imported {$condition->value}",
        ];
    }

    $import = runContainerReturnImportJob($user, $containerReturn, $rows);
    $entries = $containerReturn->entries()->get();
    $balance = app(CustomerContainerBalanceQuery::class)->forCustomer($customer->id);

    expect($import->successful_rows)->toBe(3)
        ->and((string) $containerReturn->refresh()->state)->toBe('draft')
        ->and($containerReturn->approved_at)->toBeNull()
        ->and($entries)->toHaveCount(3)
        ->and($entries->pluck('dispatch_entry_id')->filter())->toBeEmpty()
        ->and($entries->where('cross_return', true))->toBeEmpty()
        ->and(Container::query()->currentlyDispatched()->whereKey($containers[2])->exists())->toBeTrue()
        ->and($containers[2]->refresh()->is_active)->toBeTrue()
        ->and($balance->dispatched)->toBe(3)
        ->and($balance->returned)->toBe(0)
        ->and($balance->outstanding)->toBe(3);

    Event::assertNotDispatched(ContainerReturned::class);
    Event::assertNotDispatched(ContainerDamaged::class);
    Event::assertNotDispatched(ContainerLost::class);
    Event::assertNotDispatched(ContainerReturnApproved::class);
    Event::assertNotDispatched(ContainerReturnSubmitted::class);
});

it('rechecks document state when a queued import begins', function (): void {
    $user = User::query()->create([
        'name' => 'Queued Import User',
        'email' => 'queued-import-user@example.com',
    ]);
    $customer = Customer::query()->create(['name' => 'Queued Import Customer']);
    $container = Container::factory()->create();
    createApprovedImportDispatch($customer, $user, [$container]);
    $containerReturn = ContainerReturn::factory()->draft()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
    ]);
    $containerReturn->state->transitionTo('submitted');

    actingAs($user);

    $import = runContainerReturnImportJob($user, $containerReturn, [[
        'serial' => $container->serial,
        'return_condition' => ReturnCondition::Good->value,
        'note' => 'Late row',
    ]]);
    $failedRow = FailedImportRow::query()
        ->where('import_id', $import->getKey())
        ->sole();

    expect($import->processed_rows)->toBe(1)
        ->and($import->successful_rows)->toBe(0)
        ->and($failedRow->validation_error)->toContain('only be imported into a draft')
        ->and($containerReturn->entries()->doesntExist())->toBeTrue();
});

it('rejects duplicate serials across separately processed import jobs', function (): void {
    $user = User::query()->create([
        'name' => 'Chunked Import User',
        'email' => 'chunked-import-user@example.com',
    ]);
    $customer = Customer::query()->create(['name' => 'Chunked Import Customer']);
    $container = Container::factory()->create();
    createApprovedImportDispatch($customer, $user, [$container]);
    $containerReturn = ContainerReturn::factory()->draft()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
    ]);
    $row = [[
        'serial' => $container->serial,
        'return_condition' => ReturnCondition::Good->value,
        'note' => 'Repeated across jobs',
    ]];

    actingAs($user);

    $firstImport = runContainerReturnImportJob($user, $containerReturn, $row);
    $secondImport = runContainerReturnImportJob($user, $containerReturn, $row);
    $failedRow = FailedImportRow::query()
        ->where('import_id', $secondImport->getKey())
        ->sole();

    expect($firstImport->successful_rows)->toBe(1)
        ->and($secondImport->successful_rows)->toBe(0)
        ->and($failedRow->validation_error)->toContain('has already been added')
        ->and($containerReturn->entries()->count())->toBe(1);
});
