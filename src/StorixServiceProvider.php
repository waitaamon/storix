<?php

declare(strict_types=1);

namespace Storix;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Permissions\StorixPermissions;
use Storix\Policies\ContainerPolicy;
use Storix\Policies\DispatchPolicy;

final class StorixServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('storix')
            ->hasConfigFile()
            ->hasMigrations([
                'create_containers_table',
                'create_dispatches_table',
            ])
            ->runsMigrations();
    }

    public function packageBooted(): void
    {
        Gate::policy(Container::class, ContainerPolicy::class);
        Gate::policy(Dispatch::class, DispatchPolicy::class);

        Relation::morphMap([
            'storix_container' => Container::class,
            'storix_dispatch' => Dispatch::class,
        ]);

        if (config('storix.register_permissions', true)) {
            StorixPermissions::register();
        }
    }
}
