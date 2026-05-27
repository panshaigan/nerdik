<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityType>
 */
final class ActivityTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ActivityType::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'slug' => fake()->slug,
        ];
    }
}
