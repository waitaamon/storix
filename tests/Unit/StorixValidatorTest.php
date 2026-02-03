<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Storix\DTOs\DispatchContainerDTO;
use Storix\Exceptions\StorixException;
use Storix\Models\Container;
use Storix\Services\ContainerDispatchService;
use Storix\Services\StorixValidator;
use Storix\Tests\Fixtures\Models\Customer;

uses(RefreshDatabase::class);

it('rejects dispatch for inactive containers', function (): void {
    $container = Container::query()->create([
        'name' => 'Inactive Bin',
        'serial' => 'BIN-2001',
        'is_active' => false,
    ]);

    expect(fn (): mixed => app(StorixValidator::class)->assertDispatchable($container))
        ->toThrow(StorixException::class);
});

it('resolves open dispatch items by customer', function (): void {
    $customer = Customer::query()->create(['name' => 'Resolve Co']);

    $container = Container::query()->create([
        'name' => 'Tracked Bin',
        'serial' => 'BIN-2002',
        'is_active' => true,
    ]);

    app(ContainerDispatchService::class)->dispatch(new DispatchContainerDTO(
        customerId: (int) $customer->getKey(),
        saleOrderCode: 'SO-2002',
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        containerSerials: [$container->serial],
    ));

    $open = app(StorixValidator::class)->resolveOpenDispatchItem($container, (int) $customer->getKey());

    expect((int) $open->container_id)->toBe((int) $container->getKey());
});
