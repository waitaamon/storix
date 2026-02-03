<?php

declare(strict_types=1);

namespace Storix\ContainerMovement;

use Illuminate\Support\ServiceProvider;
use Storix\ContainerMovement\Services\ContainerDispatchService;
use Storix\ContainerMovement\Services\ContainerMovementValidator;
use Storix\ContainerMovement\Services\ContainerReturnService;

final class ContainerMovementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/container-movement.php', 'container-movement');

        $this->app->singleton(ContainerMovementValidator::class);
        $this->app->singleton(ContainerDispatchService::class);
        $this->app->singleton(ContainerReturnService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/container-movement.php' => config_path('container-movement.php'),
        ], 'container-movement-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
