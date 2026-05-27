<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TagCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagCategory>
 */
final class TagCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TagCategory::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'key' => fake()->word,
        ];
    }
}
