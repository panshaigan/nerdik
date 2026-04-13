<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventEnrollmentWindow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\EventEnrollmentWindow>
 */
final class EventEnrollmentWindowFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = EventEnrollmentWindow::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Event::factory(),
            'max_activities_per_user' => fake()->optional()->randomNumber(1),
            'accumulative_activities' => fake()->randomNumber(1),
            'max_allowed_participants_per_activity' => fake()->optional()->randomNumber(),
            'starts_at' => fake()->dateTime(),
            'ends_at' => fake()->dateTime(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'deleted_by' => \App\Models\User::factory(),
        ];
    }
}
