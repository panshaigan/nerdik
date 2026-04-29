<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ActivityUser>
 */
final class ActivityUserFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = ActivityUser::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    #[\Override]
    public function definition(): array
    {
        return [
            'activity_id' => \App\Models\Activity::factory(),
            'user_id' => \App\Models\User::factory(),
            'is_absent' => fake()->randomNumber(1),
            'deleted_at' => fake()->optional()->datetime(),
            'created_by' => fake()->optional()->randomNumber(),
            'updated_by' => fake()->optional()->randomNumber(),
            'deleted_by' => fake()->optional()->randomNumber(),
        ];
    }
}
