<?php

declare(strict_types=1);

namespace Storix\DTOs;

use Carbon\CarbonImmutable;

final readonly class DispatchContainerDTO
{
    /**
     * @param  list<string>  $containerSerials
     * @param  array<string, mixed>|null  $attachments
     */
    public function __construct(
        public int $customerId,
        public string $deliveryNoteCode,
        public CarbonImmutable $transactionDate,
        public array $containerSerials,
        public ?string $notes = null,
        public ?array $attachments = null,
        public ?int $userId = null,
    ) {}

    /**
     * Build a DTO from a raw array payload, trimming and deduplicating serials.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        /** @var list<string> $serials */
        $serials = array_values(array_unique(array_filter(array_map(trim(...), (array) ($payload['container_serials'] ?? [])))));

        return new self(
            customerId: (int) $payload['customer_id'],
            deliveryNoteCode: (string) $payload['delivery_note_code'],
            transactionDate: CarbonImmutable::parse((string) $payload['transaction_date']),
            containerSerials: $serials,
            notes: isset($payload['notes']) ? (string) $payload['notes'] : null,
            attachments: isset($payload['attachments']) && is_array($payload['attachments']) ? $payload['attachments'] : null,
            userId: isset($payload['user_id']) ? (int) $payload['user_id'] : null,
        );
    }
}
