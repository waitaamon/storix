<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Data;

use Carbon\CarbonImmutable;

final readonly class DispatchLifecycleData
{
    public function __construct(
        public string $containerId,
        public string $customerId,
        public string $dispatchedBy,
        public ?string $deliveryNote = null,
        public ?string $dispatchedNote = null,
        public ?CarbonImmutable $dispatchedAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            containerId: (string) $payload['container_id'],
            customerId: (string) $payload['customer_id'],
            dispatchedBy: (string) $payload['dispatched_by'],
            deliveryNote: isset($payload['delivery_note']) ? (string) $payload['delivery_note'] : null,
            dispatchedNote: isset($payload['dispatched_note']) ? (string) $payload['dispatched_note'] : null,
            dispatchedAt: isset($payload['dispatched_at']) ? CarbonImmutable::parse((string) $payload['dispatched_at']) : null,
        );
    }
}
