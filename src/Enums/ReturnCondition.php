<?php

declare(strict_types=1);

namespace Storix\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReturnCondition: string implements HasColor, HasLabel
{
    case Good = 'good';
    case Damaged = 'damaged';
    case Lost = 'lost';

    public function getColor(): string
    {
        return match ($this) {
            self::Good => 'success',
            self::Damaged => 'warning',
            self::Lost => 'danger',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Good => 'Returned Good',
            self::Damaged => 'Returned Damaged',
            self::Lost => 'Lost',
        };
    }
}
