<?php

declare(strict_types=1);

namespace Storix\Data;

use Storix\Enums\ReturnCondition;

final readonly class AddContainerReturnEntryBySerialData
{
    public function __construct(
        public string $serial,
        public ReturnCondition|string|null $condition = ReturnCondition::Good,
        public ?string $note = null,
    ) {}

    public function normalizedSerial(): string
    {
        return mb_trim($this->serial);
    }

    public function entryData(int|string $containerId): AddContainerReturnEntryData
    {
        return new AddContainerReturnEntryData(
            containerId: $containerId,
            condition: $this->condition,
            note: $this->note,
        );
    }
}
