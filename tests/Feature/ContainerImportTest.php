<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Storix\DTOs\DispatchContainerDTO;
use Storix\Enums\ContainerConditionStatus;
use Storix\Imports\DispatchImporter;
use Storix\Imports\ReturnImporter;
use Storix\Models\Container;
use Storix\Services\ContainerDispatchService;
use Storix\Tests\Fixtures\Models\Customer;

uses(RefreshDatabase::class);

it('imports dispatch rows from excel-like payloads', function (): void {
    Customer::query()->create(['name' => 'Acme Import']);

    Container::query()->create([
        'name' => 'Reusable Bin F',
        'serial' => 'BIN-000006',
        'is_active' => true,
    ]);

    $dispatch = DispatchImporter::importRow([
        'container_serial' => 'BIN-000006',
        'customer_name' => 'Acme Import',
        'sale_order_code' => 'SO-1006',
        'dispatch_date' => '2026-02-03',
        'notes' => 'Imported dispatch',
    ]);

    expect($dispatch->items)->toHaveCount(1)
        ->and($dispatch->sale_order_code)->toBe('SO-1006');
});

it('imports return rows from excel-like payloads', function (): void {
    $customer = Customer::query()->create(['name' => 'Acme Return']);

    Container::query()->create([
        'name' => 'Reusable Bin G',
        'serial' => 'BIN-000007',
        'is_active' => true,
    ]);

    app(ContainerDispatchService::class)->dispatch(new DispatchContainerDTO(
        customerId: (int) $customer->getKey(),
        saleOrderCode: 'SO-1007',
        transactionDate: CarbonImmutable::parse('2026-02-03'),
        containerSerials: ['BIN-000007'],
    ));

    $return = ReturnImporter::importRow([
        'container_serial' => 'BIN-000007',
        'return_date' => '2026-02-03',
        'condition_status' => ContainerConditionStatus::Excellent->value,
        'notes' => 'Imported return',
    ]);

    expect($return->items)->toHaveCount(1)
        ->and($return->items->first()?->condition_status)->toBe(ContainerConditionStatus::Excellent);
});
