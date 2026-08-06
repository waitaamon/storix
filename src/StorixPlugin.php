<?php

declare(strict_types=1);

namespace Storix;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Storix\Filament\Resources\ContainerResources\ContainerResource;
use Storix\Filament\Resources\ContainerReturnEntriesResources\ContainerReturnEntryResource;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Filament\Resources\DispatchEntriesResources\DispatchEntryResource;
use Storix\Filament\Resources\DispatchResources\DispatchResource;

final class StorixPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'storix';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                ContainerResource::class,
                DispatchResource::class,
                DispatchEntryResource::class,
                ContainerReturnResource::class,
                ContainerReturnEntryResource::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Storix')
                    ->icon('heroicon-s-inbox-stack'),
            ]);
    }

    public function boot(Panel $panel): void {}
}
