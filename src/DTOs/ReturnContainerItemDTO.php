<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\DTOs;

use Storix\ContainerMovement\Enums\ContainerConditionStatus;

final readonly class ReturnContainerItemDTO
{
    public function __construct(
        public string $containerSerial,
        public ContainerConditionStatus $conditionStatus,
        public ?string $notes = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            containerSerial: trim((string) $payload['container_serial']),
            conditionStatus: ContainerConditionStatus::from((string) $payload['condition_status']),
            notes: isset($payload['notes']) ? (string) $payload['notes'] : null,
        );
    }
}
