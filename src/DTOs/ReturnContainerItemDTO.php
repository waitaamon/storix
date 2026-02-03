<?php

declare(strict_types=1);

namespace Storix\DTOs;

use Storix\Enums\ContainerConditionStatus;

final readonly class ReturnContainerItemDTO
{
    public function __construct(
        public string $containerSerial,
        public ContainerConditionStatus $conditionStatus,
        public ?string $notes = null,
    ) {}

    /**
     * Build a DTO from a raw array payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            containerSerial: mb_trim((string) $payload['container_serial']),
            conditionStatus: ContainerConditionStatus::from((string) $payload['condition_status']),
            notes: isset($payload['notes']) ? (string) $payload['notes'] : null,
        );
    }
}
