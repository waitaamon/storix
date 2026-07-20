<?php

declare(strict_types=1);

namespace Storix\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
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
        /** @var class-string<Model> $userModel */
        $userModel = Config::string('storix.models.user', 'App\\Models\\User');

        /** @var class-string<Model> $deliveryNoteModel */
        $deliveryNoteModel = Config::string('storix.models.delivery_note', 'App\\Models\\Sales\\DeliveryNote');

        return [
            'dispatched_by' => $userModel::query()->create([
                'name' => $this->faker->name(),
                'email' => $this->faker->unique()->safeEmail(),
            ])->getKey(),
            'delivery_note_id' => $deliveryNoteModel::query()->create([
                'name' => $this->faker->words(3, true),
            ])->getKey(),
            'quantity' => $this->faker->numberBetween(1, 25),
            'dispatched_at' => CarbonImmutable::instance($this->faker->dateTimeThisMonth()),
            'dispatch_note' => $this->faker->optional()->paragraph(),
        ];
    }
}
