<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CityTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CityTranslation>
 */
final class CityTranslationFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = CityTranslation::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'city_id' => \App\Models\City::factory(),
            'locale' => fake()->locale,
            'name' => fake()->name,
        ];
    }
}
