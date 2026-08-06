<?php

declare(strict_types=1);

use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Storix\Actions\AddContainerReturnEntryBySerialAction;
use Storix\Data\AddContainerReturnEntryBySerialData;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Imports\ContainerReturnEntryImporter;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, string>  $columnMap
 * @param  array<string, mixed>  $options
 */
function makeContainerReturnEntryImporter(array $columnMap, array $options): ContainerReturnEntryImporter
{
    return app(ContainerReturnEntryImporter::class, [
        'import' => new Import(),
        'columnMap' => $columnMap,
        'options' => $options,
    ]);
}

function createOutstandingReturnContainer(): Container
{
    $container = Container::factory()->create();
    $dispatch = Dispatch::factory()->create([
        'quantity' => 1,
        'state' => 'approved',
        'approved_at' => now(),
    ]);

    DispatchEntry::factory()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);

    return $container;
}

beforeEach(function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);

    actingAs(User::query()->create([
        'name' => 'Return Importer',
        'email' => fake()->unique()->safeEmail(),
    ]));
});

it('imports a mapped serial through the transactional domain action', function (): void {
    $container = createOutstandingReturnContainer();
    $containerReturn = ContainerReturn::factory()->draft()->create();
    $importer = makeContainerReturnEntryImporter(
        [
            'serial' => 'Container',
            'return_condition' => 'Condition',
            'note' => 'Notes',
        ],
        ['container_return_id' => $containerReturn->id],
    );

    $importer([
        'Container' => "  {$container->serial}  ",
        'Condition' => ' DAMAGED ',
        'Notes' => '  Bent frame.  ',
    ]);

    $entry = $containerReturn->entries()->sole();

    expect($entry->container_id)->toBe($container->id)
        ->and($entry->return_condition)->toBe(ReturnCondition::Damaged)
        ->and($entry->note)->toBe('Bent frame.')
        ->and($entry->dispatch_entry_id)->toBeNull()
        ->and($entry->cross_return)->toBeFalse();
});

it('defaults an omitted return condition to good', function (): void {
    $container = createOutstandingReturnContainer();
    $containerReturn = ContainerReturn::factory()->draft()->create();
    $importer = makeContainerReturnEntryImporter(
        ['serial' => 'serial'],
        ['container_return_id' => $containerReturn->id],
    );

    $importer(['serial' => $container->serial]);

    expect($containerReturn->entries()->sole()->return_condition)->toBe(ReturnCondition::Good);
});

it('validates return conditions and note lengths before invoking the domain action', function (): void {
    $container = createOutstandingReturnContainer();
    $containerReturn = ContainerReturn::factory()->draft()->create();
    $importer = makeContainerReturnEntryImporter(
        [
            'serial' => 'serial',
            'return_condition' => 'condition',
            'note' => 'note',
        ],
        ['container_return_id' => $containerReturn->id],
    );

    expect(fn () => $importer([
        'serial' => $container->serial,
        'condition' => 'unserviceable',
        'note' => null,
    ]))->toThrow(ValidationException::class)
        ->and(fn () => $importer([
            'serial' => $container->serial,
            'condition' => ReturnCondition::Good->value,
            'note' => str_repeat('x', 2001),
        ]))->toThrow(ValidationException::class)
        ->and($containerReturn->entries()->doesntExist())->toBeTrue();
});

it('reports an unknown serial without partially writing an entry', function (): void {
    $containerReturn = ContainerReturn::factory()->draft()->create();
    $importer = makeContainerReturnEntryImporter(
        ['serial' => 'serial'],
        ['container_return_id' => $containerReturn->id],
    );

    expect(fn () => $importer(['serial' => 'MISSING-001']))
        ->toThrow(RowImportFailedException::class, 'No container found with serial [MISSING-001].')
        ->and($containerReturn->entries()->doesntExist())->toBeTrue();
});

it('reports a serial without approved custody without partially writing an entry', function (): void {
    $container = Container::factory()->create();
    $containerReturn = ContainerReturn::factory()->draft()->create();
    $importer = makeContainerReturnEntryImporter(
        ['serial' => 'serial'],
        ['container_return_id' => $containerReturn->id],
    );

    expect(fn () => $importer(['serial' => $container->serial]))
        ->toThrow(RowImportFailedException::class, 'has no outstanding approved dispatch.')
        ->and($containerReturn->entries()->doesntExist())->toBeTrue();
});

it('rejects duplicate serials in the same target return', function (): void {
    $container = createOutstandingReturnContainer();
    $containerReturn = ContainerReturn::factory()->draft()->create();
    $importer = makeContainerReturnEntryImporter(
        ['serial' => 'serial'],
        ['container_return_id' => $containerReturn->id],
    );

    $importer(['serial' => $container->serial]);

    expect(fn () => $importer(['serial' => $container->serial]))
        ->toThrow(RowImportFailedException::class, 'has already been added')
        ->and($containerReturn->entries()->count())->toBe(1);
});

it('rejects missing targets, immutable targets, and unauthorized execution', function (): void {
    $missingTargetImporter = makeContainerReturnEntryImporter(['serial' => 'serial'], []);

    expect(fn () => $missingTargetImporter(['serial' => 'CNT-001']))
        ->toThrow(RowImportFailedException::class, 'A target container return is required');

    $submittedReturn = ContainerReturn::factory()->submitted()->create();
    $submittedImporter = makeContainerReturnEntryImporter(
        ['serial' => 'serial'],
        ['container_return_id' => $submittedReturn->id],
    );

    expect(fn () => $submittedImporter(['serial' => 'CNT-001']))
        ->toThrow(RowImportFailedException::class, 'only be imported into a draft');

    Gate::before(static fn (): bool => false);
    $draftReturn = ContainerReturn::factory()->draft()->create();
    $unauthorizedImporter = makeContainerReturnEntryImporter(
        ['serial' => 'serial'],
        ['container_return_id' => $draftReturn->id],
    );

    expect(fn () => $unauthorizedImporter(['serial' => 'CNT-001']))
        ->toThrow(RowImportFailedException::class, 'not authorized');
});

it('resolves a serial only once before storing the return entry', function (): void {
    $container = createOutstandingReturnContainer();
    $containerReturn = ContainerReturn::factory()->draft()->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(AddContainerReturnEntryBySerialAction::class)->handle(
        $containerReturn,
        new AddContainerReturnEntryBySerialData(serial: $container->serial),
    );

    $serialLookups = collect(DB::getQueryLog())->filter(
        static fn (array $query): bool => str_contains($query['query'], '"storix_containers"')
            && str_contains($query['query'], '"serial"'),
    );

    DB::disableQueryLog();

    expect($serialLookups)->toHaveCount(1);
});
