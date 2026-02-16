<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Database\Seeders;

use Illuminate\Database\Seeder;
use WaitAmon\Storix\Models\Container;

final class StorixDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(StorixPermissionSeeder::class);

        if (Container::query()->doesntExist()) {
            Container::factory()->count(20)->create();
        }
    }
}
