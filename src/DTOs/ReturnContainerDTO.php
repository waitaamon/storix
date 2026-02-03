<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\DTOs;

use Carbon\CarbonImmutable;

final readonly class ReturnContainerDTO
{
    /**
     * @param list<ReturnContainerItemDTO> $items
     * @param array<string, mixed>|null $attachments
     */
    public function __construct(
        public int $customerId,
        public CarbonImmutable $transactionDate,
        public array $items,
        public ?string $notes = null,
        public ?array $attachments = null,
        public ?int $userId = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var list<array<string, mixed>> $rawItems */
        $rawItems = array_values((array) ($payload['items'] ?? []));

        $items = array_map(
            static fn (array $item): ReturnContainerItemDTO => ReturnContainerItemDTO::fromArray($item),
            $rawItems,
        );

        return new self(
            customerId: (int) $payload['customer_id'],
            transactionDate: CarbonImmutable::parse((string) $payload['transaction_date']),
            items: $items,
            notes: isset($payload['notes']) ? (string) $payload['notes'] : null,
            attachments: isset($payload['attachments']) && is_array($payload['attachments']) ? $payload['attachments'] : null,
            userId: isset($payload['user_id']) ? (int) $payload['user_id'] : null,
        );
    }
}
