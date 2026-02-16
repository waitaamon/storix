<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Storix\Data\DispatchLifecycleData;
use Storix\Enums\DispatchStatus;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Services\DispatchLifecycleService;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

it('creates dispatch and registers a returned good status', function (): void {
    $container = Container::factory()->create();
    $customer = Customer::query()->create(['name' => 'Acme Industries']);
    $dispatcher = User::query()->create(['name' => 'Dispatch User', 'email' => 'dispatch@example.com']);
    $receiver = User::query()->create(['name' => 'Receiver User', 'email' => 'receive@example.com']);

    $service = app(DispatchLifecycleService::class);

    $dispatch = $service->createDispatch(new DispatchLifecycleData(
        containerId: $container->id,
        customerId: $customer->id,
        dispatchedBy: $dispatcher->id,
        dispatchedAt: CarbonImmutable::parse('2026-01-15 08:30:00'),
    ));

    expect($dispatch->status)->toBe(DispatchStatus::Dispatched);

    $dispatch = $service->registerReturn(
        dispatch: $dispatch,
        condition: ReturnCondition::Good,
        receivedBy: $receiver->id,
        note: 'Returned in good condition',
        returnedAt: CarbonImmutable::parse('2026-01-20 12:45:00'),
    );

    expect($dispatch->status)->toBe(DispatchStatus::ReturnedGood)
        ->and($dispatch->return_note)->toBe('Returned in good condition');
});
