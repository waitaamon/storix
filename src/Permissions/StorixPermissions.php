<?php

declare(strict_types=1);

namespace Storix\Permissions;

use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class StorixPermissions
{
    public static function register(): void
    {
        if (! class_exists(Permission::class)) {
            return;
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $guardName = (string) config('storix.permissions.guard_name', 'web');

        foreach (self::all() as $permission) {
            Permission::findOrCreate($permission, $guardName);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [
            ...self::containerPermissions(),
            ...self::dispatchPermissions(),
        ];
    }

    /** @return list<string> */
    public static function containerPermissions(): array
    {
        return [
            'viewAny.containers',
            'view.containers',
            'create.containers',
            'update.containers',
            'delete.containers',
            'restore.containers',
            'forceDelete.containers',
        ];
    }

    /** @return list<string> */
    public static function dispatchPermissions(): array
    {
        return [
            'viewAny.dispatches',
            'view.dispatches',
            'create.dispatches',
            'update.dispatches',
            'delete.dispatches',
            'restore.dispatches',
            'forceDelete.dispatches',
            'receive.containers',
        ];
    }
}
