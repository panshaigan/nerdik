<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

use Illuminate\Support\Str;

use function fake;

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
        $name = fake()->name;

        return [
            'name' => $name,
            'type' => fake()->randomElement(['venue']),
            'country_id' => Country::first(['iso_alpha2' => 'PL']),
            'address' => fake()->optional()->address,
            'is_online' => 0,
            'latitude'  => fake()->randomFloat(7, 49.00, 54.85),
            'longitude' => fake()->randomFloat(7, 14.12, 24.15),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->text,
        ];
    }
}
