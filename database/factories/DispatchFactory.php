<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use WaitAmon\Storix\Models\Container;
use WaitAmon\Storix\Models\Dispatch;

/**
 * @extends Factory<Dispatch>
 */
final class DispatchFactory extends Factory
{
    protected $model = Dispatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'container_id' => Container::factory(),
            'customer_id' => (string) Str::uuid(),
            'dispatched_by' => (string) Str::uuid(),
            'delivery_note' => $this->faker->optional()->sentence(),
            'dispatched_at' => CarbonImmutable::instance($this->faker->dateTimeThisMonth()),
            'dispatched_note' => $this->faker->optional()->paragraph(),
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
