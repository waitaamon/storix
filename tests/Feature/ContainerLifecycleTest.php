<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Storix\DTOs\DispatchContainerDTO;
use Storix\DTOs\ReturnContainerDTO;
use Storix\DTOs\ReturnContainerItemDTO;
use Storix\Enums\ContainerConditionStatus;
use Storix\Exceptions\StorixException;
use Storix\Models\Container;
use Storix\Services\ContainerDispatchService;
use Storix\Services\ContainerReturnService;
use Storix\Services\StorixValidator;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

uses(RefreshDatabase::class);

it('creates a container', function (): void {
    $container = Container::query()->create([
        'name' => 'Reusable Bin A',
        'serial' => 'BIN-000001',
        'is_active' => true,
    ]);

    expect($container->serial)->toBe('BIN-000001')
        ->and($container->is_active)->toBeTrue();
});

it('dispatches a container to a customer', function (): void {
    $customer = Customer::query()->create(['name' => 'Acme Supply']);
    $user = User::query()->create(['name' => 'Dispatcher', 'email' => 'dispatcher@example.test']);

    Container::query()->create([
        'name' => 'Reusable Bin B',
        'serial' => 'BIN-000002',
        'is_active' => true,
    ]);

    $dispatch = app(ContainerDispatchService::class)->dispatch(new DispatchContainerDTO(
        customerId: (int) $customer->getKey(),
        saleOrderCode: 'SO-1001',
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        containerSerials: ['BIN-000002'],
        userId: (int) $user->getKey(),
    ));

    expect($dispatch->items)->toHaveCount(1)
        ->and($dispatch->sale_order_code)->toBe('SO-1001')
        ->and(app(StorixValidator::class)->hasOpenDispatch(Container::query()->firstOrFail()))->toBeTrue();
});

it('prevents duplicate dispatch for an unreturned container', function (): void {
    $customer = Customer::query()->create(['name' => 'Blue Ocean']);

    Container::query()->create([
        'name' => 'Reusable Bin C',
        'serial' => 'BIN-000003',
        'is_active' => true,
    ]);

    $service = app(ContainerDispatchService::class);

    $service->dispatch(new DispatchContainerDTO(
        customerId: (int) $customer->getKey(),
        saleOrderCode: 'SO-1002',
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        containerSerials: ['BIN-000003'],
    ));

    expect(fn (): mixed => $service->dispatch(new DispatchContainerDTO(
        customerId: (int) $customer->getKey(),
        saleOrderCode: 'SO-1003',
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        containerSerials: ['BIN-000003'],
    )))->toThrow(StorixException::class);
});

it('returns a dispatched container', function (): void {
    $customer = Customer::query()->create(['name' => 'Northwind']);

    Container::query()->create([
        'name' => 'Reusable Bin D',
        'serial' => 'BIN-000004',
        'is_active' => true,
    ]);

    app(ContainerDispatchService::class)->dispatch(new DispatchContainerDTO(
        customerId: (int) $customer->getKey(),
        saleOrderCode: 'SO-1004',
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        containerSerials: ['BIN-000004'],
    ));

    $return = app(ContainerReturnService::class)->return(new ReturnContainerDTO(
        customerId: (int) $customer->getKey(),
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        items: [
            new ReturnContainerItemDTO(
                containerSerial: 'BIN-000004',
                conditionStatus: ContainerConditionStatus::Good,
            ),
        ],
    ));

    expect($return->items)->toHaveCount(1)
        ->and($return->items->first()?->condition_status)->toBe(ContainerConditionStatus::Good);
});

it('prevents duplicate return for same dispatch item', function (): void {
    $customer = Customer::query()->create(['name' => 'Global Foods']);

    Container::query()->create([
        'name' => 'Reusable Bin E',
        'serial' => 'BIN-000005',
        'is_active' => true,
    ]);

    app(ContainerDispatchService::class)->dispatch(new DispatchContainerDTO(
        customerId: (int) $customer->getKey(),
        saleOrderCode: 'SO-1005',
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        containerSerials: ['BIN-000005'],
    ));

    $service = app(ContainerReturnService::class);

    $service->return(new ReturnContainerDTO(
        customerId: (int) $customer->getKey(),
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        items: [
            new ReturnContainerItemDTO(
                containerSerial: 'BIN-000005',
                conditionStatus: ContainerConditionStatus::Good,
            ),
        ],
    ));

    expect(fn (): mixed => $service->return(new ReturnContainerDTO(
        customerId: (int) $customer->getKey(),
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        items: [
            new ReturnContainerItemDTO(
                containerSerial: 'BIN-000005',
                conditionStatus: ContainerConditionStatus::Good,
            ),
        ],
    )))->toThrow(StorixException::class);
});
