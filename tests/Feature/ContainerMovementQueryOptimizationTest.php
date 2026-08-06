<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Storix\Filament\Exports\ContainerMovementExporter;
use Storix\Filament\Resources\ContainerResources\Pages\ViewContainer;
use Storix\Filament\Resources\ContainerResources\RelationManagers\MovementsRelationManager;
use Storix\Models\Container;
use Storix\Models\ContainerMovement;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

it('keeps event relation-manager and exporter queries bounded as volume grows', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => true);

    $user = User::query()->create([
        'name' => 'Movement Query User',
        'email' => 'movement-query@example.com',
    ]);
    $smallContainer = Container::factory()->create();
    $largeContainer = Container::factory()->create();

    $createEventPair = static function (Container $container): void {
        $dispatch = Dispatch::factory()->create([
            'quantity' => 1,
            'state' => 'approved',
            'approved_at' => now(),
        ]);
        DispatchEntry::factory()->create([
            'container_id' => $container->id,
            'dispatch_id' => $dispatch->id,
        ]);
        $containerReturn = ContainerReturn::factory()->approved()->create();
        ContainerReturnEntry::factory()->create([
            'container_return_id' => $containerReturn->id,
            'container_id' => $container->id,
            'dispatch_entry_id' => null,
        ]);
    };

    $createEventPair($smallContainer);

    for ($index = 0; $index < 15; $index++) {
        $createEventPair($largeContainer);
    }

    actingAs($user);

    $measureRelationManager = static function (Container $container): array {
        $manager = Livewire::test(MovementsRelationManager::class, [
            'ownerRecord' => $container,
            'pageClass' => ViewContainer::class,
        ])->instance();

        if (! $manager instanceof MovementsRelationManager) {
            throw new LogicException('The Livewire component is not a movements relation manager.');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $movements = $manager->getTableQueryForExport()->get();

        foreach ($movements as $movement) {
            if (! $movement instanceof ContainerMovement) {
                throw new LogicException('The movement relationship returned an invalid model.');
            }

            $movement->customer->getAttribute('name');
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$movements->count(), $queryCount];
    };

    $measureExporter = static function (Container $container): array {
        DB::flushQueryLog();
        DB::enableQueryLog();

        /** @var Collection<int, ContainerMovement> $movements */
        $movements = ContainerMovementExporter::modifyQuery(
            ContainerMovement::query()->where('container_id', $container->id),
        )->get();

        foreach ($movements as $movement) {
            $movement->container->getAttribute('serial');
            $movement->customer->getAttribute('name');
        }

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$movements->count(), $queryCount];
    };

    Model::preventLazyLoading();

    try {
        [$smallManagerCount, $smallManagerQueries] = $measureRelationManager($smallContainer);
        [$largeManagerCount, $largeManagerQueries] = $measureRelationManager($largeContainer);
        [$smallExportCount, $smallExportQueries] = $measureExporter($smallContainer);
        [$largeExportCount, $largeExportQueries] = $measureExporter($largeContainer);
    } finally {
        Model::preventLazyLoading(false);
        DB::disableQueryLog();
    }

    expect($smallManagerCount)->toBe(2)
        ->and($largeManagerCount)->toBe(30)
        ->and($smallManagerQueries)->toBeLessThanOrEqual(2)
        ->and($largeManagerQueries)->toBeLessThanOrEqual($smallManagerQueries + 1)
        ->and($smallExportCount)->toBe(2)
        ->and($largeExportCount)->toBe(30)
        ->and($smallExportQueries)->toBeLessThanOrEqual(3)
        ->and($largeExportQueries)->toBeLessThanOrEqual($smallExportQueries + 1);
});
