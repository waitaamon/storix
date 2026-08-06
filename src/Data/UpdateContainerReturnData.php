<?php

declare(strict_types=1);

namespace Storix\Data;

use Carbon\CarbonInterface;

final readonly class UpdateContainerReturnData
{
    public function __construct(
        public int|string $customerId,
        public CarbonInterface|string $transactionDate,
        public ?string $note = null,
    ) {}
}
