<?php

declare(strict_types=1);

namespace Storix\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContainerMovementType: string implements HasColor, HasLabel
{
    case Dispatch = 'dispatch';
    case Return = 'return';

    public function getColor(): string
    {
        return match ($this) {
            self::Dispatch => 'info',
            self::Return => 'success',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Dispatch => 'Dispatch',
            self::Return => 'Return',
        };
    }
}
