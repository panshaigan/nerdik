<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Activity>
 */
final class ActivityFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Activity::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'activity_type_id' => \App\Models\ActivityType::factory(),
            'hosting_mode' => fake()->randomNumber(1),
            'place_id' => \App\Models\Place::factory(),
            'min_participants' => fake()->optional()->randomNumber(1),
            'max_participants' => fake()->optional()->randomNumber(1),
            'minimum_age' => fake()->optional()->randomNumber(1),
            'cancellation_deadline_in_hours' => fake()->optional()->randomNumber(1),
            'duration_in_minutes' => fake()->optional()->randomNumber(),
            'allows_observers' => fake()->randomNumber(1),
            'is_host_passive' => fake()->randomNumber(1),
            'requires_approval' => fake()->randomNumber(1),
            'price' => fake()->optional()->randomFloat(2, 0, 99999999),
            'logo_path' => fake()->optional()->word,
            'slug' => fake()->slug,
            'description' => fake()->optional()->text,
            'cancel_reason' => fake()->optional()->text,
            'starts_at' => fake()->optional()->dateTime(),
            'ends_at' => fake()->optional()->dateTime(),
            'cancelled_at' => fake()->optional()->datetime(),
            'cancelled_by' => \App\Models\User::factory(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'deleted_by' => \App\Models\User::factory(),
        ];
    }
}
