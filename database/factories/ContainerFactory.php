<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use WaitAmon\Storix\Models\Container;

/**
 * @extends Factory<Container>
 */
final class ContainerFactory extends Factory
{
    protected $model = Container::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => strtoupper($this->faker->bothify('BIN-####')),
            'serial' => strtoupper($this->faker->unique()->bothify('STRX-########')),
            'is_active' => true,
            'description' => $this->faker->optional()->sentence(),
            'metadata' => [
                'capacity_liters' => $this->faker->numberBetween(20, 200),
            ],
        ];
    }
}
