<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Slot>
 */
final class SlotFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Slot::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'event_id' => \App\Models\Event::factory(),
            'activity_id' => \App\Models\Activity::factory(),
            'place_id' => \App\Models\Place::factory(),
            'requires_approval' => fake()->randomNumber(1),
            'max_capacity' => fake()->optional()->randomNumber(1),
            'starts_at' => fake()->optional()->dateTime(),
            'ends_at' => fake()->optional()->dateTime(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'deleted_by' => \App\Models\User::factory(),
        ];
    }
}
