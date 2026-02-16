<?php

declare(strict_types=1);

namespace Storix\Database\Seeders;

use Illuminate\Database\Seeder;
use Storix\Permissions\StorixPermissions;

final class StorixPermissionSeeder extends Seeder
{
    public function run(): void
    {
        StorixPermissions::register();
    }
}
