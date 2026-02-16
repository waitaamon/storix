<?php

declare(strict_types=1);

namespace WaitAmon\Storix;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use WaitAmon\Storix\Models\Container;
use WaitAmon\Storix\Models\Dispatch;
use WaitAmon\Storix\Permissions\StorixPermissions;
use WaitAmon\Storix\Policies\ContainerPolicy;
use WaitAmon\Storix\Policies\DispatchPolicy;

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

        $this->registerPermissions();
    }

    private function registerPermissions(): void
    {
        if (! config('storix.register_permissions', true)) {
            return;
        }

        if (! class_exists(Permission::class) || ! Schema::hasTable('permissions')) {
            return;
        }

        $guardName = (string) config('storix.permissions.guard_name', 'web');

        foreach (StorixPermissions::all() as $permission) {
            Permission::findOrCreate($permission, $guardName);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
