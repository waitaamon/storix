<?php

declare(strict_types=1);

namespace Storix\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\PermissionRegistrar;
use Storix\Permissions\StorixPermissions;

#[Description('Create any missing Storix permissions')]
#[Signature('storix:sync-permissions')]
final class SyncPermissionsCommand extends Command
{
    public function handle(PermissionRegistrar $permissionRegistrar): int
    {
        $permissionModelClass = $permissionRegistrar->getPermissionClass();
        $permissionModel = new $permissionModelClass;

        if (! $permissionModel instanceof Model) {
            $this->error('The configured Spatie permission model must be an Eloquent model.');

            return self::FAILURE;
        }

        if (! $permissionModel->getConnection()->getSchemaBuilder()->hasTable($permissionModel->getTable())) {
            $this->error('The permissions table does not exist. Run the application migrations before syncing Storix permissions.');

            return self::FAILURE;
        }

        StorixPermissions::sync($permissionRegistrar);

        $guardName = Config::string('storix.permissions.guard_name', 'web');
        $permissionCount = count(StorixPermissions::all());

        $this->info("Synced {$permissionCount} Storix permissions for the [{$guardName}] guard.");

        return self::SUCCESS;
    }
}
