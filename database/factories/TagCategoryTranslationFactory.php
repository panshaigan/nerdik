<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TagCategory;
use App\Models\TagCategoryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagCategoryTranslation>
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
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'tag_category_id' => TagCategory::factory(),
            'locale' => fake()->locale,
            'label' => fake()->word,
        ];
    }
}
