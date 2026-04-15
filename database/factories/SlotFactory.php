<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

use function fake;

/**
 * @extends Factory<\App\Models\Slot>
 */
final class SlotFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Slot::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'event_id' => \App\Models\Event::factory(),
            'activity_id' => null,
            'requires_approval' => fake()->boolean(),
            'max_capacity' => fake()->numberBetween(5, 10),
            'starts_at' => fake()->optional()->dateTime(),
            'ends_at' => fake()->optional()->dateTime(),
            'created_by' => \App\Models\User::factory(),
        ];
    }

    public function consistentWithEventAndPlace(): static
    {
        return $this->afterCreating(function (Slot $slot) {
            $event = $slot->event;

            if (!$event) return;

            $startsAt = fake()->dateTimeBetween($event->starts_at, $event->ends_at)
                ->setTime(fake()->numberBetween(12, 14), 0, 0);

            $slot->update([
                'starts_at'  => $startsAt,
                'ends_at'    => (clone $startsAt)->modify('+4 hours'),
                'created_by' => $event->created_by,
                'place_id' => $event->place_id,
            ]);
        });
    }

    public function withActivityTypesAttached($activityTypes)
    {
        return $this->afterCreating(function (Slot $slot) use ($activityTypes) {
            $slot->activityTypes()->attach(
                $activityTypes->random(rand(1, min(3, $activityTypes->count())))->pluck('id')
            );
        });
    }
}
