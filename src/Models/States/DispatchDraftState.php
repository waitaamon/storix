<?php

declare(strict_types=1);

namespace Storix\Models\States;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

final class DispatchDraftState extends DispatchState implements HasColor, HasDescription, HasIcon, HasLabel
{
    public static string $name = 'draft';

    public static int $order = 1;

    public function getLabel(): string
    {
        return __('Draft');
    }

    public function getIcon(): string
    {
        return 'heroicon-o-pencil-square';
    }

    public function getColor(): array
    {
        return Color::Gray;
    }

    public function getDescription(): string
    {
        return 'Return this dispatch to draft.';
    }
}
