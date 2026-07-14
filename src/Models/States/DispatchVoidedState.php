<?php

declare(strict_types=1);

namespace Storix\Models\States;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

final class DispatchVoidedState extends DispatchState implements HasColor, HasDescription, HasIcon, HasLabel
{
    public static string $name = 'voided';

    public static int $order = 9;

    public function getLabel(): string
    {
        return __('Voided');
    }

    public function getIcon(): string
    {
        return 'heroicon-o-no-symbol';
    }

    public function getColor(): array
    {
        return Color::Red;
    }

    public function getDescription(): string
    {
        return 'Void this dispatch with a documented reason.';
    }
}
