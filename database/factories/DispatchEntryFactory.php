<?php

declare(strict_types=1);

namespace Storix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;

final class DispatchEntryFactory extends Factory
{
    protected $model = DispatchEntry::class;

    public function definition(): array
    {
        return [
            'container_id' => Container::factory(),
            'dispatch_id' => Dispatch::factory(),
        ];
    }
}
