<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TagCategoryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TagCategoryTranslation>
 */
final class TagCategoryTranslationFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = TagCategoryTranslation::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'tag_category_id' => \App\Models\TagCategory::factory(),
            'locale' => fake()->locale,
            'label' => fake()->word,
        ];
    }
}
