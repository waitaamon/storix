<?php

declare(strict_types=1);

namespace Storix;

use Illuminate\Support\ServiceProvider;
use Storix\Services\ContainerDispatchService;
use Storix\Services\ContainerReturnService;
use Storix\Services\StorixValidator;

final class StorixServiceProvider extends ServiceProvider
{
    /** Register config, validator, and service singletons. */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/storix.php', 'storix');

        $this->app->singleton(StorixValidator::class);
        $this->app->singleton(ContainerDispatchService::class);
        $this->app->singleton(ContainerReturnService::class);
    }

    /** Publish config and load migrations. */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/storix.php' => config_path('storix.php'),
        ], 'storix-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
