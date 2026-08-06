<?php

declare(strict_types=1);

namespace Storix\Models\States;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

final class ContainerReturnSubmittedState extends ContainerReturnState implements HasColor, HasDescription, HasIcon, HasLabel
{
    public static string $name = 'submitted';

    public static int $order = 2;

    public function getLabel(): string
    {
        return __('Submitted');
    }

    public function getIcon(): string
    {
        return 'heroicon-o-paper-airplane';
    }

    public function getColor(): array
    {
        return Color::Amber;
    }

    public function getDescription(): string
    {
        return 'This return is awaiting independent approval.';
    }
}
