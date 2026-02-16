<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Enums;

enum ReturnCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Returned Good',
            self::Damaged => 'Returned Damaged',
            self::Lost => 'Lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Good => 'success',
            self::Damaged => 'warning',
            self::Lost => 'danger',
        };
    }
}
