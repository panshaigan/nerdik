<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityUser>
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
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'user_id' => User::factory(),
            'is_absent' => fake()->randomNumber(1),
            'deleted_at' => fake()->optional()->datetime(),
            'created_by' => fake()->optional()->randomNumber(),
            'updated_by' => fake()->optional()->randomNumber(),
            'deleted_by' => fake()->optional()->randomNumber(),
        ];
    }
}
