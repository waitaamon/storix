<?php

declare(strict_types=1);

namespace Storix\Tests\Fixtures\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use Storix\StorixPlugin;

final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('test')
            ->path('test')
            ->plugin(StorixPlugin::make());
    }
}
