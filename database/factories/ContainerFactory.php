<?php

declare(strict_types=1);

namespace Storix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Storix\Models\Container;

/**
 * @extends Factory<Container>
 */
final class ContainerFactory extends Factory
{
    protected $model = Container::class;

    public function definition(): array
    {
        return [
            'name' => 'Container '.$this->faker->numerify('####'),
            'serial' => mb_strtoupper(Str::random(12)),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
