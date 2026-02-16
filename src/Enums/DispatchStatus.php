<?php

declare(strict_types=1);

namespace Storix\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DispatchStatus: string implements HasColor, HasLabel
{
    case Dispatched = 'dispatched';
    case ReturnedGood = 'returned_good';
    case ReturnedDamaged = 'returned_damaged';
    case Lost = 'lost';

    public static function fromReturnCondition(?ReturnCondition $condition): self
    {
        return match ($condition) {
            ReturnCondition::Good => self::ReturnedGood,
            ReturnCondition::Damaged => self::ReturnedDamaged,
            ReturnCondition::Lost => self::Lost,
            null => self::Dispatched,
        };
    }

    public function getColor(): string|null|array
    {
        return match ($this) {
            self::Dispatched => 'info',
            self::ReturnedGood => 'success',
            self::ReturnedDamaged => 'warning',
            self::Lost => 'danger',
        };
    }

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Dispatched => 'Dispatched',
            self::ReturnedGood => 'Returned Good',
            self::ReturnedDamaged => 'Returned Damaged',
            self::Lost => 'Lost',
        };
    }
}
