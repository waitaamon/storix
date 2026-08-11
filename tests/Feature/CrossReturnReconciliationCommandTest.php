<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Config::set(
        'storix.cross_return_reconciliation.report_directory',
        storage_path('framework/testing/storix-cross-return-reconciliation/'.Str::ulid()),
    );
});

afterEach(function (): void {
    $directory = Config::get('storix.cross_return_reconciliation.report_directory');

    if (is_string($directory)) {
        File::deleteDirectory($directory);
    }
});

function reconciliationCustomer(string $name): Customer
{
    return Customer::query()->create(['name' => $name]);
}

function reconciliationUser(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => Str::slug($name).'-'.Str::ulid().'@example.com',
    ]);
}

function reconciliationDispatch(
    Container $container,
    Customer $customer,
    string $timestamp,
    ?string $code = null,
): DispatchEntry {
    $dispatcher = reconciliationUser('Reconciliation Dispatcher');
    $dispatch = Dispatch::query()->create([
        'customer_id' => $customer->getKey(),
        'delivery_note_id' => null,
        'dispatched_by' => $dispatcher->getKey(),
        'code' => $code,
        'quantity' => 1,
        'dispatched_at' => $timestamp,
        'state' => 'approved',
        'approved_by' => $dispatcher->getKey(),
        'approved_at' => $timestamp,
    ]);

    return DispatchEntry::query()->create([
        'dispatch_id' => $dispatch->getKey(),
        'container_id' => $container->getKey(),
    ]);
}

function reconciliationReturn(
    Container $container,
    Customer $customer,
    string $date,
    bool $crossReturn,
    ?DispatchEntry $dispatchEntry = null,
    string $state = 'approved',
    ?string $code = null,
): ContainerReturnEntry {
    $preparer = reconciliationUser('Reconciliation Preparer');
    $approver = reconciliationUser('Reconciliation Approver');
    $containerReturn = ContainerReturn::query()->create([
        'code' => $code,
        'customer_id' => $customer->getKey(),
        'user_id' => $preparer->getKey(),
        'approved_by' => $state === 'approved' ? $approver->getKey() : null,
        'approved_at' => $state === 'approved' ? "{$date} 17:00:00" : null,
        'state' => $state,
        'transaction_date' => $date,
    ]);
    $entry = new ContainerReturnEntry();
    $entry->forceFill([
        'container_return_id' => $containerReturn->getKey(),
        'container_id' => $container->getKey(),
        'dispatch_entry_id' => $dispatchEntry?->getKey(),
        'return_condition' => ReturnCondition::Good,
        'cross_return' => $crossReturn,
    ])->save();

    return $entry;
}

/**
 * @return list<string>
 */
function reconciliationReportLines(?string $path = null): array
{
    $directory = $path ?? Config::string('storix.cross_return_reconciliation.report_directory');
    $files = File::files($directory);

    if ($files === []) {
        throw new RuntimeException('No reconciliation report was written.');
    }

    $report = $files[count($files) - 1];
    $lines = file($report->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (! is_array($lines)) {
        throw new RuntimeException('The reconciliation report could not be read.');
    }

    return $lines;
}

/**
 * @return array<int, array<string, mixed>>
 */
function reconciliationReportEntries(?string $path = null): array
{
    return array_map(
        static function (string $line): array {
            $contextOffset = mb_strpos($line, ' {"event":');

            if ($contextOffset === false
                || preg_match(
                    '/^\[[^]]+\] .+\.[A-Z]+: Storix cross-return reconciliation .+\.$/',
                    mb_substr($line, 0, $contextOffset),
                ) !== 1) {
                throw new RuntimeException('The reconciliation report entry is not in Laravel log format.');
            }

            $entry = json_decode(
                mb_substr($line, $contextOffset + 1),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            if (! is_array($entry)) {
                throw new RuntimeException('The reconciliation report entry is not an object.');
            }

            return $entry;
        },
        reconciliationReportLines($path),
    );
}

function storixReconciliationSchedule(): ?Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains(
            (string) $event->command,
            'storix:reconcile-cross-returns',
        ));
}

it('corrects a false cross return to the latest same-customer physical dispatch', function (): void {
    $container = Container::factory()->create();
    $returningCustomer = reconciliationCustomer('Returning Customer');
    $otherCustomer = reconciliationCustomer('Older Customer');
    $olderEntry = reconciliationDispatch($container, $otherCustomer, '2026-07-01 09:00:00', 'DSP-OLDER');
    $latestEntry = reconciliationDispatch($container, $returningCustomer, '2026-07-25 09:00:00', 'DSP-LATEST');
    $returnEntry = reconciliationReturn(
        $container,
        $returningCustomer,
        '2026-08-07',
        true,
        $olderEntry,
        code: 'CRN-FALSE-CROSS',
    );

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $returnEntry->refresh();
    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($returnEntry->dispatch_entry_id)->toBe($latestEntry->id)
        ->and($returnEntry->cross_return)->toBeFalse()
        ->and($candidate['status'] ?? null)->toBe('reconciled')
        ->and($candidate['database_correction'] ?? null)->toBeTrue()
        ->and($candidate['selected_dispatch']['entry_id'] ?? null)->toBe($latestEntry->id)
        ->and($candidate['reason'] ?? null)->toContain('false cross-return flag was cleared');
});

it('reconciles the 20210358 regression cycle and leaves imported history unchanged', function (): void {
    $container = Container::factory()->create(['serial' => '20210358']);
    $olderCustomer = reconciliationCustomer('Older Customer');
    $marketingTeam = reconciliationCustomer('Marketing Team');
    $olderEntry = reconciliationDispatch($container, $olderCustomer, '2026-06-15 08:00:00', 'DSP-OLD-CUSTOMER');
    $historicalReturn = reconciliationReturn(
        $container,
        $olderCustomer,
        '2026-06-20',
        false,
        code: 'CRN-IMPORTED',
    );
    $marketingEntry = reconciliationDispatch(
        $container,
        $marketingTeam,
        '2026-07-25 10:30:00',
        'DSP-MARKETING',
    );
    $augustReturn = reconciliationReturn(
        $container,
        $marketingTeam,
        '2026-08-07',
        true,
        $olderEntry,
        code: 'CRN-AUGUST',
    );
    $historicalUpdatedAt = $historicalReturn->getRawOriginal('updated_at');

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($augustReturn->refresh()->dispatch_entry_id)->toBe($marketingEntry->id)
        ->and($augustReturn->cross_return)->toBeFalse()
        ->and($historicalReturn->refresh()->dispatch_entry_id)->toBeNull()
        ->and($historicalReturn->cross_return)->toBeFalse()
        ->and($historicalReturn->getRawOriginal('updated_at'))->toBe($historicalUpdatedAt)
        ->and($candidate['container']['serial'] ?? null)->toBe('20210358')
        ->and($candidate['previous_dispatch']['code'] ?? null)->toBe('DSP-OLD-CUSTOMER')
        ->and($candidate['selected_dispatch']['code'] ?? null)->toBe('DSP-MARKETING')
        ->and($candidate['returning_customer']['name'] ?? null)->toBe('Marketing Team');
});

it('confirms an unchanged genuine cross return', function (): void {
    $container = Container::factory()->create();
    $dispatchCustomer = reconciliationCustomer('Dispatch Customer');
    $returnCustomer = reconciliationCustomer('Return Customer');
    $dispatchEntry = reconciliationDispatch($container, $dispatchCustomer, '2026-07-01 08:00:00');
    $returnEntry = reconciliationReturn($container, $returnCustomer, '2026-07-02', true, $dispatchEntry);
    $updatedAt = $returnEntry->getRawOriginal('updated_at');

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($returnEntry->refresh()->dispatch_entry_id)->toBe($dispatchEntry->id)
        ->and($returnEntry->cross_return)->toBeTrue()
        ->and($returnEntry->getRawOriginal('updated_at'))->toBe($updatedAt)
        ->and($candidate['status'] ?? null)->toBe('confirmed_cross_return')
        ->and($candidate['database_correction'] ?? null)->toBeFalse();
});

it('corrects a wrong dispatch link while preserving a genuine cross return', function (): void {
    $container = Container::factory()->create();
    $olderCustomer = reconciliationCustomer('Older Customer');
    $latestCustomer = reconciliationCustomer('Latest Customer');
    $returnCustomer = reconciliationCustomer('Different Return Customer');
    $olderEntry = reconciliationDispatch($container, $olderCustomer, '2026-07-01 08:00:00');
    $latestEntry = reconciliationDispatch($container, $latestCustomer, '2026-07-03 08:00:00');
    $returnEntry = reconciliationReturn($container, $returnCustomer, '2026-07-04', true, $olderEntry);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($returnEntry->refresh()->dispatch_entry_id)->toBe($latestEntry->id)
        ->and($returnEntry->cross_return)->toBeTrue()
        ->and($candidate['status'] ?? null)->toBe('reconciled')
        ->and($candidate['cross_return'] ?? null)->toBe(['before' => true, 'after' => true]);
});

it('ignores non-cross returns and unapproved cross returns', function (string $state, bool $crossReturn): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('Ignored Customer');
    $dispatchEntry = reconciliationDispatch($container, $customer, '2026-07-01 08:00:00');
    $returnEntry = reconciliationReturn(
        $container,
        $customer,
        '2026-07-02',
        $crossReturn,
        $dispatchEntry,
        $state,
    );

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS)
        ->and($returnEntry->refresh()->cross_return)->toBe($crossReturn)
        ->and(collect(reconciliationReportEntries())->where('event', 'candidate'))->toBeEmpty();
})->with([
    'ordinary approved return' => ['approved', false],
    'draft cross return' => ['draft', true],
    'submitted cross return' => ['submitted', true],
]);

it('reports a missing approved dispatch without modifying the entry', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('No Dispatch Customer');
    $returnEntry = reconciliationReturn($container, $customer, '2026-07-02', true);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($returnEntry->refresh()->dispatch_entry_id)->toBeNull()
        ->and($returnEntry->cross_return)->toBeTrue()
        ->and($candidate['status'] ?? null)->toBe('discrepancy')
        ->and($candidate['reason'] ?? null)->toContain('No approved physical dispatch');
});

it('reports an approved dispatch whose physical timestamp is missing', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('Missing Timestamp Customer');
    reconciliationDispatch($container, $customer, '2026-06-30 08:00:00');
    $dispatchEntry = reconciliationDispatch($container, $customer, '2026-07-01 08:00:00');
    DB::table(TableNames::dispatches())
        ->where('id', $dispatchEntry->dispatch_id)
        ->update(['dispatched_at' => null]);
    $returnEntry = reconciliationReturn($container, $customer, '2026-07-02', true);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($returnEntry->refresh()->dispatch_entry_id)->toBeNull()
        ->and($returnEntry->cross_return)->toBeTrue()
        ->and($candidate['status'] ?? null)->toBe('discrepancy')
        ->and($candidate['selected_dispatch']['entry_id'] ?? null)->toBe($dispatchEntry->id)
        ->and($candidate['reason'] ?? null)->toContain('no physical dispatch timestamp')
        ->and($candidate['reason'] ?? null)->toContain('latest cycle cannot be proved');
});

it('does not reconcile duplicate latest physical dispatch timestamps', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('Duplicate Timestamp Customer');
    $olderCustomer = reconciliationCustomer('Older Customer');
    $olderEntry = reconciliationDispatch($container, $olderCustomer, '2026-07-01 08:00:00');
    reconciliationDispatch($container, $customer, '2026-07-03 08:00:00');
    reconciliationDispatch($container, $customer, '2026-07-03 08:00:00');
    $returnEntry = reconciliationReturn($container, $customer, '2026-07-04', true, $olderEntry);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($returnEntry->refresh()->dispatch_entry_id)->toBe($olderEntry->id)
        ->and($returnEntry->cross_return)->toBeTrue()
        ->and($candidate['status'] ?? null)->toBe('discrepancy')
        ->and($candidate['reason'] ?? null)->toContain('share the latest physical dispatch timestamp');
});

it('does not infer same-date event order from a date-only return', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('Same Date Customer');
    $otherCustomer = reconciliationCustomer('Other Customer');
    $olderEntry = reconciliationDispatch($container, $otherCustomer, '2026-07-01 08:00:00');
    reconciliationDispatch($container, $customer, '2026-07-03 08:00:00');
    $returnEntry = reconciliationReturn($container, $customer, '2026-07-03', true, $olderEntry);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($returnEntry->refresh()->dispatch_entry_id)->toBe($olderEntry->id)
        ->and($candidate['status'] ?? null)->toBe('discrepancy')
        ->and($candidate['reason'] ?? null)->toContain('date-only return cannot prove event order');
});

it('does not reconcile across an intervening approved return', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('Intervening Customer');
    $otherCustomer = reconciliationCustomer('Other Customer');
    $olderEntry = reconciliationDispatch($container, $otherCustomer, '2026-07-01 08:00:00');
    reconciliationDispatch($container, $customer, '2026-07-03 08:00:00');
    reconciliationReturn($container, $customer, '2026-07-04', false);
    $candidateEntry = reconciliationReturn($container, $customer, '2026-07-05', true, $olderEntry);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($candidateEntry->refresh()->dispatch_entry_id)->toBe($olderEntry->id)
        ->and($candidate['status'] ?? null)->toBe('discrepancy')
        ->and($candidate['reason'] ?? null)->toContain('Another approved return may occur');
});

it('does not use a proposed dispatch already linked to another approved return', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('Already Returned Customer');
    $otherCustomer = reconciliationCustomer('Other Customer');
    $olderEntry = reconciliationDispatch($container, $otherCustomer, '2026-07-01 08:00:00');
    $proposedEntry = reconciliationDispatch($container, $customer, '2026-07-03 08:00:00');
    reconciliationReturn($container, $customer, '2026-07-04', false, $proposedEntry);
    $candidateEntry = reconciliationReturn($container, $customer, '2026-07-05', true, $olderEntry);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($candidateEntry->refresh()->dispatch_entry_id)->toBe($olderEntry->id)
        ->and($candidate['status'] ?? null)->toBe('discrepancy')
        ->and($candidate['reason'] ?? null)->toContain('already linked to another approved return');
});

it('performs dry-run analysis without database changes', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('Dry Run Customer');
    $otherCustomer = reconciliationCustomer('Dry Run Other Customer');
    $olderEntry = reconciliationDispatch($container, $otherCustomer, '2026-07-01 08:00:00');
    $latestEntry = reconciliationDispatch($container, $customer, '2026-07-03 08:00:00');
    $returnEntry = reconciliationReturn($container, $customer, '2026-07-04', true, $olderEntry);

    expect(Artisan::call('storix:reconcile-cross-returns', ['--dry-run' => true]))
        ->toBe(Command::SUCCESS);

    $candidate = collect(reconciliationReportEntries())->firstWhere('event', 'candidate');

    expect($returnEntry->refresh()->dispatch_entry_id)->toBe($olderEntry->id)
        ->and($returnEntry->cross_return)->toBeTrue()
        ->and($candidate['status'] ?? null)->toBe('reconcilable_dry_run')
        ->and($candidate['database_correction'] ?? null)->toBeFalse()
        ->and($candidate['selected_dispatch']['entry_id'] ?? null)->toBe($latestEntry->id);
});

it('is idempotent across repeated execution', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('Idempotent Customer');
    $otherCustomer = reconciliationCustomer('Idempotent Other Customer');
    $olderEntry = reconciliationDispatch($container, $otherCustomer, '2026-07-01 08:00:00');
    $latestEntry = reconciliationDispatch($container, $customer, '2026-07-03 08:00:00');
    $returnEntry = reconciliationReturn($container, $customer, '2026-07-04', true, $olderEntry);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $updatedAt = $returnEntry->refresh()->getRawOriginal('updated_at');

    expect($returnEntry->dispatch_entry_id)->toBe($latestEntry->id)
        ->and($returnEntry->cross_return)->toBeFalse()
        ->and(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS)
        ->and($returnEntry->refresh()->getRawOriginal('updated_at'))->toBe($updatedAt);

    $completion = collect(reconciliationReportEntries())->last();

    expect($completion['totals']['evaluated'] ?? null)->toBe(0)
        ->and($completion['totals']['database_corrections'] ?? null)->toBe(0);
});

it('creates a unique report filename for every execution', function (): void {
    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS)
        ->and(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $files = File::files(Config::string('storix.cross_return_reconciliation.report_directory'));

    expect($files)->toHaveCount(2)
        ->and($files[0]->getFilename())->not->toBe($files[1]->getFilename())
        ->and($files[0]->getFilename())->toMatch('/^cross-return-reconciliation-\d{8}_\d{6}_\d{6}-[0-9A-Z]{26}\.log$/');
});

it('writes start, successful candidate, candidate failure, and completion entries', function (): void {
    $customer = reconciliationCustomer('Report Customer');
    $otherCustomer = reconciliationCustomer('Report Other Customer');
    $differentReturnCustomer = reconciliationCustomer('Report Different Return Customer');
    $failingContainer = Container::factory()->create();
    $failingOlderEntry = reconciliationDispatch(
        $failingContainer,
        $otherCustomer,
        '2026-07-01 08:00:00',
    );
    reconciliationDispatch($failingContainer, $customer, '2026-07-03 08:00:00');
    reconciliationReturn(
        $failingContainer,
        $differentReturnCustomer,
        '2026-07-04',
        true,
        $failingOlderEntry,
    );
    DB::unprepared(<<<'SQL'
        CREATE TRIGGER fail_storix_reconciliation_update
        BEFORE UPDATE OF dispatch_entry_id, cross_return ON storix_container_return_entries
        WHEN NEW.cross_return = 1
        BEGIN
            SELECT RAISE(FAIL, 'simulated reconciliation write failure');
        END
        SQL);

    $validContainer = Container::factory()->create();
    $olderEntry = reconciliationDispatch($validContainer, $otherCustomer, '2026-07-01 08:00:00');
    reconciliationDispatch($validContainer, $customer, '2026-07-03 08:00:00');
    reconciliationReturn($validContainer, $customer, '2026-07-04', true, $olderEntry);

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::FAILURE);

    $lines = reconciliationReportLines();
    $entries = collect(reconciliationReportEntries());
    $start = $entries->firstWhere('event', 'start');
    $success = $entries->firstWhere('status', 'reconciled');
    $failure = $entries->firstWhere('status', 'failed');
    $completion = $entries->firstWhere('event', 'completion');
    $failureIndex = $entries->search(fn (array $entry): bool => $entry === $failure);
    $successIndex = $entries->search(fn (array $entry): bool => $entry === $success);

    if (! is_int($failureIndex) || ! is_int($successIndex)) {
        throw new RuntimeException('Expected reconciliation report entries were not found.');
    }

    expect($start['dry_run'] ?? null)->toBeFalse()
        ->and($lines[0] ?? null)->toMatch(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] .+\.INFO: Storix cross-return reconciliation started\. \{.*\}(?: (?:\[.*\]|\{.*\}))?\s*$/',
        )
        ->and(implode("\n", $lines))->toContain(
            '.ERROR: Storix cross-return reconciliation candidate failed.',
        )
        ->and($start['run_id'] ?? null)->toBeString()
        ->and($success['event'] ?? null)->toBe('candidate')
        ->and($failure['event'] ?? null)->toBe('candidate')
        ->and($failure['exception']['message'] ?? null)->toContain('simulated reconciliation write failure')
        ->and($failureIndex)->toBeLessThan($successIndex)
        ->and($completion['totals']['failed'] ?? null)->toBe(1);
});

it('stores report and execution metadata only in files', function (): void {
    $container = Container::factory()->create();
    $customer = reconciliationCustomer('File Only Customer');
    $otherCustomer = reconciliationCustomer('File Only Other Customer');
    $olderEntry = reconciliationDispatch($container, $otherCustomer, '2026-07-01 08:00:00');
    reconciliationDispatch($container, $customer, '2026-07-03 08:00:00');
    reconciliationReturn($container, $customer, '2026-07-04', true, $olderEntry);
    $tablesBefore = collect(Schema::getTables())->pluck('name')->sort()->values()->all();
    $rowCountsBefore = collect($tablesBefore)->mapWithKeys(
        fn (string $table): array => [$table => DB::table($table)->count()],
    );

    expect(Artisan::call('storix:reconcile-cross-returns'))->toBe(Command::SUCCESS);

    $tablesAfter = collect(Schema::getTables())->pluck('name')->sort()->values()->all();
    $rowCountsAfter = collect($tablesAfter)->mapWithKeys(
        fn (string $table): array => [$table => DB::table($table)->count()],
    );

    expect($tablesAfter)->toBe($tablesBefore)
        ->and($rowCountsAfter->all())->toBe($rowCountsBefore->all())
        ->and(File::files(Config::string('storix.cross_return_reconciliation.report_directory')))->toHaveCount(1);
});

it('registers the reconciliation command through the package', function (): void {
    expect(Artisan::all())->toHaveKey('storix:reconcile-cross-returns');
});

it('schedules reconciliation daily at midnight with configured controls', function (): void {
    Config::set('storix.cross_return_reconciliation.schedule.timezone', 'Africa/Nairobi');
    $event = storixReconciliationSchedule();

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event?->expression)->toBe('0 0 * * *')
        ->and($event?->timezone)->toBe('Africa/Nairobi')
        ->and($event?->withoutOverlapping)->toBeTrue()
        ->and($event?->expiresAt)->toBe(120)
        ->and($event?->onOneServer)->toBeTrue();
});

it('allows package-owned scheduling to be disabled', function (): void {
    Config::set('storix.cross_return_reconciliation.schedule.enabled', false);

    expect(storixReconciliationSchedule())->toBeNull();
});
