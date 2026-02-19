<?php

declare(strict_types=1);

namespace Storix\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Storix\Models\Dispatch;

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
            'customer_id' => $this->faker->numberBetween(1, 1000000),
            'dispatched_by' => $this->faker->numberBetween(1, 1000000),
            'delivery_note_id' => $this->faker->numberBetween(1, 1000000),
            'dispatched_at' => CarbonImmutable::instance($this->faker->dateTimeThisMonth()),
            'dispatched_note' => $this->faker->optional()->paragraph(),
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
