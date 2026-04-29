<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Tag>
 */
final class TagFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Tag::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    #[\Override]
    public function definition(): array
    {
        return [
            'tag_category_id' => \App\Models\TagCategory::factory(),
            'logo_path' => fake()->optional()->word,
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'deleted_by' => \App\Models\User::factory(),
        ];
    }
}
