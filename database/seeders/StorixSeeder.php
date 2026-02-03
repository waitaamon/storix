<?php

declare(strict_types=1);

namespace Storix\Database\Seeders;

use Illuminate\Database\Seeder;
use Storix\Models\Container;

final class StorixSeeder extends Seeder
{
    public function run(): void
    {
        $payload = [];

        for ($index = 1; $index <= 50; $index++) {
            $payload[] = [
                'name' => sprintf('Reusable Bin %03d', $index),
                'serial' => sprintf('RBIN-%06d', $index),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Container::query()->upsert($payload, ['serial'], ['name', 'is_active', 'updated_at']);
    }
}
