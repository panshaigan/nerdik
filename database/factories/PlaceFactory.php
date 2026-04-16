<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Place;
use App\Models\User;
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
            'slug' => Str::slug($name),
            'address' => fake()->optional()->streetAddress(), // street and number
            'city_id' => null,
            'description' => fake()->optional()->text,
            'latitude'  => null,
            'longitude' => null,
        ];
    }

    public function poland(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_id' => Country::where('iso_alpha2', 'PL')->first()->id,
            'latitude'  => fake()->randomFloat(7, 49.00, 54.85),
            'longitude' => fake()->randomFloat(7, 14.12, 24.15),
        ]);
    }

    public function venue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'venue',
        ]);
    }

    public function room(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'room',
        ]);
    }
}
