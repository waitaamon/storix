<?php

declare(strict_types=1);

namespace Storix\Database\Seeders;

use Illuminate\Database\Seeder;
use Storix\Models\Container;

final class StorixDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(StorixPermissionSeeder::class);

        if (Container::query()->doesntExist() && app()->isLocal()) {
            Container::factory()->count(20)->create();
        }
    }
}
