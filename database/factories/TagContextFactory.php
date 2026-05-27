<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag;
use App\Models\TagContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagContext>
 */
final class TagContextFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TagContext::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'tag_id' => Tag::factory(),
            'context_type' => fake()->word,
            'context_id' => fake()->randomNumber(),
        ];
    }
}
