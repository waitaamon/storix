<?php

declare(strict_types=1);

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Jobs\ExportCompletion;
use Filament\Actions\Exports\Jobs\ExportCsv;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery\MockInterface;
use Storix\Filament\Exports\CustomerContainerBalanceExporter;
use Storix\Filament\Pages\CustomerContainerBalances;
use Storix\Filament\Tables\CustomerContainerBalancesTable;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Permissions\StorixPermissions;
use Storix\Support\CustomerContainerBalanceQuery;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\CustomerWithFinancialBalance;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

function balanceExportUser(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::ulid().'@example.com',
    ]);
}

function balanceExportDispatch(Customer $customer, int $quantity): void
{
    $dispatcher = balanceExportUser('Balance Export Dispatcher');
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
                'name' => 'Balance Export '.Str::ulid(),
                'serial' => 'EXP-'.Str::ulid(),
            ])->getKey(),
        ]);
    }
}

/** @return array<string, array{isEnabled: bool, label: string}> */
function balanceExportColumnMap(): array
{
    return [
        'name' => ['isEnabled' => true, 'label' => 'Customer'],
        'dispatched' => ['isEnabled' => true, 'label' => 'Dispatched'],
        'returned' => ['isEnabled' => true, 'label' => 'Returned'],
        'lost' => ['isEnabled' => true, 'label' => 'Lost'],
        'balance' => ['isEnabled' => true, 'label' => 'Balance'],
    ];
}

/** @return array<string, string> */
function balanceExportColumnLabels(): array
{
    return collect(balanceExportColumnMap())
        ->mapWithKeys(static fn (array $column, string $name): array => [
            $name => $column['label'],
        ])
        ->all();
}

beforeEach(function (): void {
    Filament::setCurrentPanel('test');
});

it('defines the dynamic customer balance exporter and completion summary', function (): void {
    $columns = collect(CustomerContainerBalanceExporter::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();
    $export = new Export([
        'total_rows' => 5,
        'successful_rows' => 4,
    ]);

    expect(CustomerContainerBalanceExporter::getModel())->toBe(Customer::class)
        ->and($columns)->toBe([
            'name',
            'dispatched',
            'returned',
            'lost',
            'balance',
        ])->and(CustomerContainerBalanceExporter::getCompletedNotificationBody($export))
        ->toBe('Customer container balance export finished: 4 successful rows, 1 failed rows.');
});

it('authorizes the bulk export with the dedicated report permission', function (): void {
    Gate::define(
        StorixPermissions::VIEW_ANY_CUSTOMER_CONTAINER_BALANCES,
        static fn (User $user): bool => $user->getAttribute('name') === 'Authorized Export User',
    );

    $authorized = balanceExportUser('Authorized Export User');
    $unauthorized = balanceExportUser('Unauthorized Export User');

    actingAs($authorized);

    /** @var HasTable&MockInterface $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $authorizedAction = CustomerContainerBalancesTable::configure(Table::make($livewire))
        ->getBulkAction('export');

    expect($authorizedAction)->toBeInstanceOf(ExportBulkAction::class)
        ->and($authorizedAction?->isAuthorized())->toBeTrue();

    actingAs($unauthorized);

    expect(CustomerContainerBalances::canAccess())->toBeFalse();
});

it('exports only selected filtered rows through the serializable aggregate query', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);
    Config::set('queue.default', 'sync');
    Config::set('filament.default_filesystem_disk', 'local');
    Storage::fake('local');

    $user = balanceExportUser('Balance Export User');
    $selected = Customer::query()->create(['name' => '=Formula Customer']);
    $unselected = Customer::query()->create(['name' => 'Unselected Customer']);
    balanceExportDispatch($selected, 2);
    balanceExportDispatch($unselected, 1);

    Config::set('storix.models.customer', CustomerWithFinancialBalance::class);

    actingAs($user);

    $export = new Export([
        'file_disk' => 'local',
        'file_name' => 'customer-container-balances',
        'exporter' => CustomerContainerBalanceExporter::class,
        'total_rows' => 1,
    ]);
    $export->user()->associate($user);
    $export->save();

    $query = app(CustomerContainerBalanceQuery::class)
        ->forReport()
        ->where('customers.name', 'like', '%Formula%');
    $columnMap = balanceExportColumnLabels();

    $job = new ExportCsv(
        export: $export,
        query: EloquentSerializeFacade::serialize($query),
        records: [$selected->getKey()],
        page: 1,
        columnMap: $columnMap,
    );
    $job->handle();

    $export->refresh();

    $completion = new ExportCompletion(
        export: $export,
        columnMap: $columnMap,
        formats: [ExportFormat::Csv],
        options: [],
        authGuard: 'web',
    );
    $completion->handle();

    $export->refresh();
    $csvFiles = collect(Storage::disk('local')->allFiles($export->getFileDirectory()))
        ->filter(static fn (string $file): bool => str_ends_with($file, '.csv'))
        ->reject(static fn (string $file): bool => str_ends_with($file, 'headers.csv'))
        ->values();

    expect($export->exporter)->toBe(CustomerContainerBalanceExporter::class)
        ->and($export->total_rows)->toBe(1)
        ->and($export->successful_rows)->toBe(1)
        ->and($export->completed_at)->not->toBeNull()
        ->and($csvFiles)->toHaveCount(1);

    $csv = Storage::disk('local')->get($csvFiles->firstOrFail());

    if (! is_string($csv)) {
        throw new RuntimeException('The customer container balance CSV was not written.');
    }

    $row = str_getcsv(mb_trim($csv), escape: '\\');

    expect($row)->toBe(["'=Formula Customer", '2', '0', '0', '2'])
        ->and($csv)->not->toContain('Unselected Customer');
});

it('executes the selected filtered bulk export synchronously from the livewire page', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);
    Config::set('queue.default', 'sync');
    Config::set('filament.default_filesystem_disk', 'local');
    Storage::fake('local');

    $user = balanceExportUser('Livewire Balance Export User');
    $selected = Customer::query()->create(['name' => 'Livewire Selected Customer']);
    $unselected = Customer::query()->create(['name' => 'Livewire Unselected Customer']);
    balanceExportDispatch($selected, 3);
    balanceExportDispatch($unselected, 1);

    actingAs($user);

    $page = Livewire::test(CustomerContainerBalances::class)->instance();

    if (! $page instanceof CustomerContainerBalances) {
        throw new LogicException('The customer balance page did not mount.');
    }

    $page->tableSearch = 'Livewire';
    $page->mountTableBulkAction('export', [$selected->getKey()]);
    $form = $page->getMountedTableBulkActionForm();

    if (! $form instanceof Schema) {
        throw new LogicException('The customer balance export form did not mount.');
    }

    $form->fill(['columnMap' => balanceExportColumnMap()]);
    $page->callMountedTableBulkAction();

    $export = Export::query()->sole();
    $csvFiles = collect(Storage::disk('local')->allFiles($export->getFileDirectory()))
        ->filter(static fn (string $file): bool => str_ends_with($file, '.csv'))
        ->reject(static fn (string $file): bool => str_ends_with($file, 'headers.csv'))
        ->values();

    expect($export->exporter)->toBe(CustomerContainerBalanceExporter::class)
        ->and($export->total_rows)->toBe(1)
        ->and($export->successful_rows)->toBe(1)
        ->and($export->completed_at)->not->toBeNull()
        ->and($csvFiles)->toHaveCount(1);

    $csv = Storage::disk('local')->get($csvFiles->firstOrFail());

    if (! is_string($csv)) {
        throw new RuntimeException('The Livewire customer balance CSV was not written.');
    }

    expect($csv)->toContain('Livewire Selected Customer')
        ->and($csv)->not->toContain('Livewire Unselected Customer');
});

it('keeps the export query count bounded as report volume grows', function (): void {
    for ($index = 0; $index < 20; $index++) {
        $customer = Customer::query()->create([
            'name' => "Export Volume Customer {$index}",
        ]);
        balanceExportDispatch($customer, 2);
    }

    Gate::before(static fn (mixed $user, string $ability): bool => true);
    actingAs(balanceExportUser('Export Volume User'));

    $page = Livewire::test(CustomerContainerBalances::class)->instance();

    if (! $page instanceof CustomerContainerBalances) {
        throw new LogicException('The customer balance page did not mount.');
    }

    Model::preventLazyLoading();
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $records = CustomerContainerBalanceExporter::modifyQuery(
            $page->getTableQueryForExport(),
        )->get();
        $queryCount = count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        Model::preventLazyLoading(false);
    }

    expect($records)->toHaveCount(20)
        ->and($queryCount)->toBe(1);
});
