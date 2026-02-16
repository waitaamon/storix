<?php

declare(strict_types=1);

namespace Storix\Data;

use Carbon\CarbonImmutable;

final readonly class DispatchLifecycleData
{
    public function __construct(
        public int $containerId,
        public int $customerId,
        public int $dispatchedBy,
        public ?string $deliveryNote = null,
        public ?string $dispatchedNote = null,
        public ?CarbonImmutable $dispatchedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            containerId: (int) $payload['container_id'],
            customerId: (int) $payload['customer_id'],
            dispatchedBy: (int) $payload['dispatched_by'],
            deliveryNote: isset($payload['delivery_note']) ? (string) $payload['delivery_note'] : null,
            dispatchedNote: isset($payload['dispatched_note']) ? (string) $payload['dispatched_note'] : null,
            dispatchedAt: isset($payload['dispatched_at']) ? CarbonImmutable::parse((string) $payload['dispatched_at']) : null,
        );
    }
}
