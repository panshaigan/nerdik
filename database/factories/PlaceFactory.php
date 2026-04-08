<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Place>
 */
final class PlaceFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Place::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'type' => fake()->word,
            'country_id' => \App\Models\Country::factory(),
            'city_id' => \App\Models\City::factory(),
            'parent_id' => \App\Models\Place::factory(),
            'address' => fake()->optional()->address,
            'links' => fake()->optional()->word,
            'is_online' => fake()->randomNumber(1),
            'latitude' => fake()->optional()->randomFloat(7, 0, 999),
            'longitude' => fake()->optional()->randomFloat(7, 0, 999),
            'logo_path' => fake()->optional()->word,
            'slug' => fake()->slug,
            'description' => fake()->optional()->text,
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'deleted_by' => \App\Models\User::factory(),
        ];
    }
}
