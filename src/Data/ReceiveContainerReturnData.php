<?php

declare(strict_types=1);

namespace Storix\Data;

use Carbon\CarbonInterface;
use Storix\Enums\ReturnCondition;

final readonly class ReceiveContainerReturnData
{
    public function __construct(
        public CarbonInterface|string $returnDate,
        public ReturnCondition|string $condition,
        public int|string|null $receivedBy = null,
        public ?string $note = null,
    ) {}
}
