<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TagRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TagRelation>
 */
final class TagRelationFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = TagRelation::class;

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
            'related_tag_id' => \App\Models\Tag::factory(),
        ];
    }
}
