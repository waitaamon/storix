<?php

declare(strict_types=1);

namespace Storix\Data;

use Carbon\CarbonInterface;

final readonly class CreateContainerReturnData
{
    public function __construct(
        public int|string $customerId,
        public int|string $userId,
        public CarbonInterface|string $transactionDate,
        public ?string $note = null,
    ) {}
}
