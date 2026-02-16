<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Enums;

enum DispatchStatus: string
{
    case Dispatched = 'dispatched';
    case ReturnedGood = 'returned_good';
    case ReturnedDamaged = 'returned_damaged';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Dispatched => 'Dispatched',
            self::ReturnedGood => 'Returned Good',
            self::ReturnedDamaged => 'Returned Damaged',
            self::Lost => 'Lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Dispatched => 'info',
            self::ReturnedGood => 'success',
            self::ReturnedDamaged => 'warning',
            self::Lost => 'danger',
        };
    }

    public static function fromReturnCondition(?ReturnCondition $condition): self
    {
        return match ($condition) {
            ReturnCondition::Good => self::ReturnedGood,
            ReturnCondition::Damaged => self::ReturnedDamaged,
            ReturnCondition::Lost => self::Lost,
            null => self::Dispatched,
        };
    }
}
