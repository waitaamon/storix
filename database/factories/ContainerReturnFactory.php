<?php

declare(strict_types=1);

namespace Storix\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Storix\Models\ContainerReturn;

/**
 * @extends Factory<ContainerReturn>
 */
final class ContainerReturnFactory extends Factory
{
    protected $model = ContainerReturn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var class-string<Model> $customerModel */
        $customerModel = Config::string('storix.models.customer', 'App\\Models\\Sales\\Customer');

        /** @var class-string<Model> $userModel */
        $userModel = Config::string('storix.models.user', 'App\\Models\\User');

        return [
            'customer_id' => $customerModel::query()->create([
                'name' => $this->faker->company(),
            ])->getKey(),
            'user_id' => $userModel::query()->create([
                'name' => $this->faker->name(),
                'email' => $this->faker->unique()->safeEmail(),
            ])->getKey(),
            'transaction_date' => CarbonImmutable::instance($this->faker->dateTimeThisMonth())->startOfDay(),
            'note' => $this->faker->optional()->sentence(),
            'state' => 'draft',
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'state' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function submitted(): static
    {
        return $this->state([
            'state' => 'submitted',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(function (): array {
            /** @var class-string<Model> $userModel */
            $userModel = Config::string('storix.models.user', 'App\\Models\\User');

            return [
                'state' => 'approved',
                'approved_by' => $userModel::query()->create([
                    'name' => $this->faker->name(),
                    'email' => $this->faker->unique()->safeEmail(),
                ])->getKey(),
                'approved_at' => now(),
            ];
        });
    }
}
