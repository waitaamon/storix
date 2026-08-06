<?php

declare(strict_types=1);

namespace Storix\Data;

use Carbon\CarbonInterface;

final readonly class CreateDispatchData
{
    /**
     * @param  list<int|string>  $containerIds
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int|string|null $deliveryNoteId,
        public int|string $dispatchedBy,
        public int $quantity,
        public int|string $customerId,
        public CarbonInterface|string|null $dispatchedAt = null,
        public ?string $dispatchNote = null,
        public array $containerIds = [],
        public ?string $idempotencyKey = null,
        public array $metadata = [],
        public bool $checkAvailability = true
    ) {}
}
