<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\City;
use App\Models\CityTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CityTranslation>
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
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'locale' => fake()->locale,
            'name' => fake()->name,
        ];
    }
}
