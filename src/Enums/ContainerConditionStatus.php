<?php

declare(strict_types=1);

namespace Storix\Enums;

enum ContainerConditionStatus: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case Damaged = 'damaged';
    case Lost = 'lost';

    /**
     * Get all backed string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
