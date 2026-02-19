<?php

declare(strict_types=1);

namespace Storix\Permissions;

use Illuminate\Support\Facades\Config;
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

        $guardName = Config::string('storix.permissions.guard_name', 'web');

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
            ...self::dispatchEntryPermissions(),
        ];
    }

    /** @return list<string> */
    public static function containerPermissions(): array
    {
        return [
            'manage.containers',
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
            'manage.dispatches',
            'viewAny.dispatches',
            'view.dispatches',
            'create.dispatches',
            'update.dispatches',
            'delete.dispatches',
            'restore.dispatches',
            'forceDelete.dispatches',
            'receive.containers',
            'draft.dispatches',
            'approve.dispatches',
        ];
    }

    /** @return list<string> */
    public static function dispatchEntryPermissions(): array
    {
        return [
            'manage.dispatch-entries',
            'viewAny.dispatch-entries',
            'view.dispatch-entries',
            'create.dispatch-entries',
            'update.dispatch-entries',
            'delete.dispatch-entries',
            'restore.dispatch-entries',
            'forceDelete.dispatch-entries',
        ];
    }
}
