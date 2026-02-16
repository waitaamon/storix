<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use WaitAmon\Storix\Permissions\StorixPermissions;

final class StorixPermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! class_exists(Permission::class)) {
            return;
        }

        $guardName = (string) config('storix.permissions.guard_name', 'web');

        foreach (StorixPermissions::all() as $permission) {
            Permission::findOrCreate($permission, $guardName);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
