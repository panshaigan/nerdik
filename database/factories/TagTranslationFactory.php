<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag;
use App\Models\TagTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagTranslation>
 */
final class TagTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TagTranslation::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'tag_id' => Tag::factory(),
            'locale' => fake()->locale,
            'label' => fake()->word,
            'slug' => fake()->optional()->slug,
        ];
    }
}
