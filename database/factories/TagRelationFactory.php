<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tag;
use App\Models\TagRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagRelation>
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
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'tag_id' => Tag::factory(),
            'related_tag_id' => Tag::factory(),
        ];
    }
}
