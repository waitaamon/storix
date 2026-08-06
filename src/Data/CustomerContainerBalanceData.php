<?php

declare(strict_types=1);

namespace Storix\Data;

final readonly class CustomerContainerBalanceData
{
    public function __construct(
        public int $dispatched,
        public int $returned,
        public int $lost,
        public int $outstanding,
    ) {}
}
