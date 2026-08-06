<?php

declare(strict_types=1);

namespace Storix\Data;

use Storix\Enums\ReturnCondition;

final readonly class AddContainerReturnEntryData
{
    public function __construct(
        public int|string $containerId,
        public ReturnCondition|string|null $condition = ReturnCondition::Good,
        public ?string $note = null,
    ) {}

    public function returnCondition(): ReturnCondition
    {
        if ($this->condition instanceof ReturnCondition) {
            return $this->condition;
        }

        if ($this->condition === null) {
            return ReturnCondition::Good;
        }

        return ReturnCondition::from($this->condition);
    }
}
