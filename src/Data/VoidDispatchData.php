<?php

declare(strict_types=1);

namespace Storix\Data;

final readonly class VoidDispatchData
{
    public function __construct(
        public int|string|null $voidedBy,
        public string $reason,
    ) {}
}
