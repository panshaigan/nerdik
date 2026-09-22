<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\EntityLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityLink>
 */
final class EntityLinkFactory extends Factory
{
    protected $model = EntityLink::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'linkable_type' => 'activity',
            'linkable_id' => Activity::factory(),
            'name' => fake()->words(2, true),
            'url' => fake()->url(),
            'sort_order' => 0,
        ];
    }
}
