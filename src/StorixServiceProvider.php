<?php

declare(strict_types=1);

namespace Storix;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Permissions\StorixPermissions;
use Storix\Policies\ContainerPolicy;
use Storix\Policies\DispatchEntryPolicy;
use Storix\Policies\DispatchPolicy;

final class StorixServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('storix')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageBooted(): void
    {
        Gate::policy(Container::class, ContainerPolicy::class);
        Gate::policy(Dispatch::class, DispatchPolicy::class);
        Gate::policy(DispatchEntry::class, DispatchEntryPolicy::class);

        Relation::morphMap([
            'storix_container' => Container::class,
            'storix_dispatch' => Dispatch::class,
            'storix_dispatch_entry' => DispatchEntry::class,
        ]);

        if (Config::boolean('storix.permissions.register', true)) {
            StorixPermissions::register();
        }
    }
}
