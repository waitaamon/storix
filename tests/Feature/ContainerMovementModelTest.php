<?php

declare(strict_types=1);

use Storix\Enums\ContainerMovementType;
use Storix\Models\Container;
use Storix\Models\ContainerMovement;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\Customer;

it('configures movement events as immutable string-keyed view models', function (): void {
    $movement = new ContainerMovement();

    expect(config('storix.models.container_movement'))->toBe(ContainerMovement::class)
        ->and(config('storix.labels.container_movement'))->toBe('container movement')
        ->and($movement->getTable())->toBe(TableNames::containerMovements())
        ->and($movement->getKeyType())->toBe('string')
        ->and($movement->getIncrementing())->toBeFalse()
        ->and($movement->usesTimestamps())->toBeFalse()
        ->and($movement->getGuarded())->toBe(['*']);
});

it('casts dispatch and return events and resolves their customers', function (): void {
    $dispatchCustomer = Customer::query()->create(['name' => 'Model Dispatch Customer']);
    $returnCustomer = Customer::query()->create(['name' => 'Model Return Customer']);
    $container = Container::factory()->create();
    $dispatch = Dispatch::factory()->create([
        'customer_id' => $dispatchCustomer->id,
        'quantity' => 1,
        'state' => 'approved',
        'dispatched_at' => '2026-08-04 09:00:00',
        'approved_at' => now(),
    ]);
    $dispatchEntry = DispatchEntry::factory()->create([
        'container_id' => $container->id,
        'dispatch_id' => $dispatch->id,
    ]);
    $containerReturn = ContainerReturn::factory()->approved()->create([
        'customer_id' => $returnCustomer->id,
        'transaction_date' => '2026-08-05',
    ]);
    $returnEntry = ContainerReturnEntry::factory()->crossReturn()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $container->id,
        'dispatch_entry_id' => null,
    ]);

    $movements = ContainerMovement::query()
        ->with(['container', 'customer'])
        ->where('container_id', $container->id)
        ->orderBy('movement_date')
        ->get();
    $dispatchMovement = $movements->first();
    $returnMovement = $movements->last();

    expect($movements)->toHaveCount(2)
        ->and($dispatchMovement?->getKey())->toBe("dispatch:{$dispatchEntry->id}")
        ->and($dispatchMovement?->document_type)->toBe(ContainerMovementType::Dispatch)
        ->and($dispatchMovement?->movement_date->toDateString())->toBe('2026-08-04')
        ->and($dispatchMovement?->customer->is($dispatchCustomer))->toBeTrue()
        ->and($dispatchMovement?->cross_return)->toBeNull()
        ->and($returnMovement?->getKey())->toBe("return:{$returnEntry->id}")
        ->and($returnMovement?->document_type)->toBe(ContainerMovementType::Return)
        ->and($returnMovement?->movement_date->toDateString())->toBe('2026-08-05')
        ->and($returnMovement?->customer->is($returnCustomer))->toBeTrue()
        ->and($returnMovement?->cross_return)->toBeTrue();
});

it('scopes all dispatch and return events through their owning container', function (): void {
    $owner = Container::factory()->create();
    $other = Container::factory()->create();
    $dispatch = Dispatch::factory()->create([
        'quantity' => 1,
        'state' => 'approved',
        'approved_at' => now(),
    ]);
    DispatchEntry::factory()->create([
        'container_id' => $owner->id,
        'dispatch_id' => $dispatch->id,
    ]);
    $containerReturn = ContainerReturn::factory()->approved()->create();
    ContainerReturnEntry::factory()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $owner->id,
        'dispatch_entry_id' => null,
    ]);
    DispatchEntry::factory()->create([
        'container_id' => $other->id,
        'dispatch_id' => Dispatch::factory()->create([
            'quantity' => 1,
            'state' => 'approved',
            'approved_at' => now(),
        ])->id,
    ]);

    expect($owner->movements()->pluck('container_id')->all())->toBe([
        $owner->id,
        $owner->id,
    ])->and($owner->movements)->toHaveCount(2);
});

it('rejects model-level writes to movement view records', function (): void {
    $container = Container::factory()->create();
    $dispatch = Dispatch::factory()->create([
        'quantity' => 1,
        'state' => 'approved',
        'approved_at' => now(),
    ]);
    DispatchEntry::factory()->create([
        'container_id' => $container->id,
        'dispatch_id' => $dispatch->id,
    ]);

    $movement = ContainerMovement::query()->sole();

    expect(fn () => $movement->forceFill(['document_code' => 'Mutation'])->save())
        ->toThrow(LogicException::class, 'Container movements are read-only database view records.')
        ->and(fn () => $movement->delete())
        ->toThrow(LogicException::class, 'Container movements are read-only database view records.');
});
