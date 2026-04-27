<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

use function fake;

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
            'name' => fake()->name,
            'event_id' => Event::factory(),
            'max_activities_per_user' => fake()->numberBetween(0, 2),
            'accumulative_activities' => fake()->boolean(),
            'max_allowed_participants_per_activity' => fake()->numberBetween(0, 2),
            'starts_at' => fake()->dateTime(),
            'ends_at' => fake()->dateTime(),
        ];
    }

    public function consistentWithEvent(): static
    {
        return $this->afterCreating(function (EventEnrollmentWindow $window) {
            $event = $window->event;

            if (!$event) return;

            $window->update([
                'starts_at'  => fake()->dateTimeBetween('now', '+1 week')
                    ->setTime(fake()->numberBetween(9, 17), 0, 0),
                'ends_at'    => $event->ends_at,
                'created_by' => $event->created_by,
            ]);
        });
    }
}
