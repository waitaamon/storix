<?php

declare(strict_types=1);

use Storix\DTOs\DispatchContainerDTO;
use Storix\DTOs\ReturnContainerDTO;
use Storix\Enums\ContainerConditionStatus;

it('maps dispatch dto payloads cleanly', function (): void {
    $dto = DispatchContainerDTO::fromArray([
        'customer_id' => 99,
        'delivery_note_code' => 'SO-3001',
        'transaction_date' => '2026-02-03',
        'container_serials' => [' A ', 'A', 'B '],
        'notes' => 'note',
    ]);

    expect($dto->customerId)->toBe(99)
        ->and($dto->containerSerials)->toBe(['A', 'B']);
});

it('maps return dto payloads cleanly', function (): void {
    $dto = ReturnContainerDTO::fromArray([
        'customer_id' => 12,
        'transaction_date' => '2026-02-03',
        'items' => [
            [
                'container_serial' => 'BIN-10',
                'condition_status' => ContainerConditionStatus::Damaged->value,
            ],
        ],
    ]);

    expect($dto->items)->toHaveCount(1)
        ->and($dto->items[0]->conditionStatus)->toBe(ContainerConditionStatus::Damaged);
});
