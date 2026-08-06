<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Storix\Filament\Exports\ContainerReturnEntryExporter;
use Storix\Filament\Exports\ContainerReturnExporter;
use Storix\Filament\Resources\ContainerReturnEntriesResources\ContainerReturnEntryResource;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Filament\Resources\ContainerReturnResources\Pages\ViewContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\RelationManagers\EntriesRelationManager;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\SpreadsheetSafeText;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

it('eager loads return resource relationships with a bounded query count', function (): void {
    ContainerReturn::factory()
        ->count(8)
        ->approved()
        ->create()
        ->each(fn (ContainerReturn $return) => ContainerReturnEntry::factory()
            ->count(2)
            ->create(['container_return_id' => $return->id]));

    Model::preventLazyLoading();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $returns = ContainerReturnResource::getEloquentQuery()->get();
    $returnDetails = [];

    foreach ($returns as $return) {
        if (! $return instanceof ContainerReturn) {
            throw new LogicException('The configured container return resource model is invalid.');
        }

        $returnDetails[] = [
            $return->customer->getAttribute('name'),
            $return->user->getAttribute('name'),
            $return->approvedBy?->getAttribute('name'),
            $return->entries_count,
        ];
    }

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    Model::preventLazyLoading(false);

    expect($returns)->toHaveCount(8)
        ->and($returnDetails)->toHaveCount(8)
        ->and($queryCount)->toBeLessThanOrEqual(4);
});

it('eager loads return entry resources and exporters without per-row queries', function (): void {
    $containerReturns = ContainerReturn::factory()->count(6)->approved()->create();

    foreach ($containerReturns as $containerReturn) {
        ContainerReturnEntry::factory()->create(['container_return_id' => $containerReturn->id]);
    }

    Model::preventLazyLoading();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $entries = ContainerReturnEntryResource::getEloquentQuery()->get();
    $entryDetails = [];

    foreach ($entries as $entry) {
        if (! $entry instanceof ContainerReturnEntry) {
            throw new LogicException('The configured container return entry resource model is invalid.');
        }

        $entryDetails[] = [
            $entry->container->serial,
            $entry->containerReturn->customer->getAttribute('name'),
            $entry->dispatchEntry?->dispatch?->customer?->getAttribute('name'),
        ];
    }

    ContainerReturnExporter::modifyQuery(ContainerReturn::query())->get();
    ContainerReturnEntryExporter::modifyQuery(ContainerReturnEntry::query())->get();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    Model::preventLazyLoading(false);

    expect($entries)->toHaveCount(6)
        ->and($entryDetails)->toHaveCount(6)
        ->and($queryCount)->toBeLessThanOrEqual(13);
});

it('neutralizes spreadsheet formula prefixes while retaining ordinary values', function (): void {
    expect(SpreadsheetSafeText::sanitize('=HYPERLINK("bad")'))->toBe('\'=HYPERLINK("bad")')
        ->and(SpreadsheetSafeText::sanitize('+1-555-0100'))->toBe('\'+1-555-0100')
        ->and(SpreadsheetSafeText::sanitize('@malicious'))->toBe('\'@malicious')
        ->and(SpreadsheetSafeText::sanitize('Ordinary note'))->toBe('Ordinary note')
        ->and(SpreadsheetSafeText::sanitize(42))->toBe(42);
});

it('keeps relation-manager relationship queries bounded as entry volume grows', function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);

    $user = User::query()->create([
        'name' => 'Query Count User',
        'email' => 'query-count-user@example.com',
    ]);
    $smallReturn = ContainerReturn::factory()->draft()->create(['user_id' => $user->id]);
    $largeReturn = ContainerReturn::factory()->draft()->create(['user_id' => $user->id]);

    foreach ([[$smallReturn, 1], [$largeReturn, 15]] as [$containerReturn, $entryCount]) {
        for ($index = 0; $index < $entryCount; $index++) {
            $container = Container::factory()->create();
            $dispatch = Dispatch::factory()->create([
                'quantity' => 1,
                'state' => 'approved',
                'approved_at' => now(),
            ]);
            $dispatchEntry = DispatchEntry::factory()->create([
                'dispatch_id' => $dispatch->id,
                'container_id' => $container->id,
            ]);

            ContainerReturnEntry::factory()->create([
                'container_return_id' => $containerReturn->id,
                'container_id' => $container->id,
                'dispatch_entry_id' => $dispatchEntry->id,
            ]);
        }
    }

    actingAs($user);

    $measureQueries = static function (ContainerReturn $containerReturn): array {
        $manager = Livewire::test(EntriesRelationManager::class, [
            'ownerRecord' => $containerReturn,
            'pageClass' => ViewContainerReturn::class,
        ])->instance();

        if (! $manager instanceof EntriesRelationManager) {
            throw new LogicException('The Livewire component is not a return entries relation manager.');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $entries = $manager->getTableQueryForExport()->get();
        $loadedRelationshipValues = [];

        foreach ($entries as $entry) {
            if (! $entry instanceof ContainerReturnEntry) {
                throw new LogicException('The configured container return entry model is invalid.');
            }

            $loadedRelationshipValues[] = [
                $entry->container->serial,
                $entry->dispatchEntry?->dispatch->customer->getAttribute('name'),
            ];
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$entries->count(), count($loadedRelationshipValues), $queryCount];
    };

    Model::preventLazyLoading();

    try {
        [$smallEntryCount, $smallLoadedCount, $smallQueryCount] = $measureQueries($smallReturn);
        [$largeEntryCount, $largeLoadedCount, $largeQueryCount] = $measureQueries($largeReturn);
    } finally {
        Model::preventLazyLoading(false);
        DB::disableQueryLog();
    }

    expect($smallEntryCount)->toBe(1)
        ->and($largeEntryCount)->toBe(15)
        ->and($smallLoadedCount)->toBe($smallEntryCount)
        ->and($largeLoadedCount)->toBe($largeEntryCount)
        ->and($smallQueryCount)->toBeLessThanOrEqual(6)
        ->and($largeQueryCount)->toBeLessThanOrEqual($smallQueryCount + 1);
});
