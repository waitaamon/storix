<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Permissions;

final class StorixPermissions
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            ...self::containerPermissions(),
            ...self::dispatchPermissions(),
        ];
    }

    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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
