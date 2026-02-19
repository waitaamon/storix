<?php

declare(strict_types=1);

namespace Storix\Models\States;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

final class DispatchApprovedState extends DispatchState implements HasColor, HasDescription, HasIcon, HasLabel
{
    public static string $name = 'approved';

    public static int $order = 3;

    public function getLabel(): ?string
    {
        return __('Approved');
    }

    public function getIcon(): string
    {
        return 'heroicon-o-check-circle';
    }

    public function getColor(): array
    {
        return Color::Green;
    }

    public function getDescription(): string
    {
        return 'Approve this dispatch.';
    }
}
