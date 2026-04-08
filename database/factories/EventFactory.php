<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Event>
 */
final class EventFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Event::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'name' => fake()->optional()->name,
            'organization_id' => \App\Models\Organization::factory(),
            'is_public' => fake()->randomNumber(1),
            'logo_path' => fake()->optional()->word,
            'slug' => fake()->slug,
            'description' => fake()->optional()->text,
            'starts_at' => fake()->dateTime(),
            'ends_at' => fake()->dateTime(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'deleted_by' => \App\Models\User::factory(),
        ];
    }
}
