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
        public int|string $deliveryNoteId,
        public int|string $dispatchedBy,
        public CarbonInterface|string|null $dispatchedAt = null,
        public ?string $dispatchNote = null,
        public array $containerIds = [],
        public ?string $idempotencyKey = null,
        public array $metadata = [],
    ) {}
}
