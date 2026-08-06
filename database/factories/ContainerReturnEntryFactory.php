<?php

declare(strict_types=1);

namespace Storix\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;

/**
 * @extends Factory<ContainerReturnEntry>
 */
final class ContainerReturnEntryFactory extends Factory
{
    protected $model = ContainerReturnEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'container_return_id' => ContainerReturn::factory(),
            'container_id' => Container::factory(),
            'return_condition' => ReturnCondition::Good,
            'note' => $this->faker->optional()->sentence(),
            'cross_return' => false,
        ];
    }

    public function damaged(): static
    {
        return $this->state(['return_condition' => ReturnCondition::Damaged]);
    }

    public function lost(): static
    {
        return $this->state(['return_condition' => ReturnCondition::Lost]);
    }

    public function crossReturn(): static
    {
        return $this->state(['cross_return' => true]);
    }
}
