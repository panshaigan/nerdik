<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TagAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TagAlias>
 */
final class TagAliasFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = TagAlias::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    #[\Override]
    public function definition(): array
    {
        return [
            'tag_id' => \App\Models\Tag::factory(),
            'locale' => fake()->optional()->locale,
            'alias' => fake()->word,
        ];
    }
}
