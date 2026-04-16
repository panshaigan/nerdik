<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

use function fake;

/**
 * @extends Factory<Slot>
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
            'event_id' => Event::factory(),
            'activity_id' => null,
            'requires_approval' => fake()->boolean(),
            'max_capacity' => fake()->numberBetween(5, 10),
            'starts_at' => fake()->optional()->dateTime(),
            'ends_at' => fake()->optional()->dateTime(),
            'created_by' => User::factory(),
        ];
    }

    public function consistentWithEventAndPlace(): self
    {
        return $this->afterCreating(function (Slot $slot) {
            $event = $slot->event;

            if (!$event) {
                return;
            }

            $startsAt = fake()->dateTimeBetween($event->starts_at, $event->ends_at)
                ->setTime(fake()->randomElement([12, 14]), fake()->randomElement([0, 30]));

            $slot->update([
                'starts_at'  => $startsAt,
                'ends_at'    => (clone $startsAt)->modify('+4 hours'),
                'created_by' => $event->created_by,
                'place_id' => $event->place_id,
            ]);
        });
    }

    public function withActivityTypesAttached(Collection $activityTypes): self
    {
        return $this->afterCreating(function (Slot $slot) use ($activityTypes) {
            $slot->activityTypes()->attach(
                $activityTypes->random(random_int(1, min(3, $activityTypes->count())))->pluck('id')
            );
        });
    }
}
