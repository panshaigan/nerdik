<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Country;
use App\Models\CountryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountryTranslation>
 */
final class CountryTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CountryTranslation::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'locale' => fake()->locale,
            'name' => fake()->name,
        ];
    }
}
