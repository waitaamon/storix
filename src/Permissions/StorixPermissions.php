<?php

declare(strict_types=1);

namespace Storix\Permissions;

use Deprecated;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;

final class StorixPermissions
{
    public static function sync(?PermissionRegistrar $permissionRegistrar = null): void
    {
        $guardName = Config::string('storix.permissions.guard_name', 'web');
        $permissionRegistrar ??= app(PermissionRegistrar::class);

        /** @var class-string<Permission> $permissionModelClass */
        $permissionModelClass = $permissionRegistrar->getPermissionClass();

        foreach (self::all() as $permission) {
            $permissionModelClass::findOrCreate($permission, $guardName);
        }

        $permissionRegistrar->forgetCachedPermissions();
    }

    #[Deprecated(message: 'Use sync() instead.')]
    public static function register(): void
    {
        self::sync();
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
            'approve.dispatches',
            'void.dispatches',
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
            'receive.dispatch-entries',
        ];
    }
}
