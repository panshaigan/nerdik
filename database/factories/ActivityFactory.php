<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

use function fake;
use function min;
use function rand;

/**
 * @extends Factory<\App\Models\Activity>
 */
final class ActivityFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Activity::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        $name = fake()->name;

        return [
            'name' => $name,
            'activity_type_id' => ActivityType::findBySlug('rpg'),
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,

            'min_participants' => fake()->numberBetween(0, 3),
            'max_participants' => fake()->numberBetween(3, 10),
            'minimum_age' => fake()->optional(0.3)->randomElement([
                12,
                16, 16,
                18, 18, 18, 18
            ]),
            'cancellation_deadline_in_hours' => fake()->optional()->randomElement([
                12,
                18, 18,
                24, 24, 24, 24
            ]),
            'duration_in_minutes' => fake()->randomElement([
                120,
                150,
                180, 180,
                240, 240, 240, 240,
            ]),
            'allows_observers' => fake()->boolean(),
            'is_host_passive' => 0,
            'requires_approval' => fake()->boolean(0.3),
            'price' => null,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->text,
            'created_by' => User::factory(),
        ];
    }

    public function selfHosted($users)
    {
        return $this->afterCreating(function (Activity $activity) use ($users) {
            $startsAt = fake()->dateTimeBetween('+1 week', '+6 months')
                ->setTime(fake()->numberBetween(9, 17), 0, 0);

            $startsAt = \Carbon\Carbon::instance($startsAt);

            $endsAt = (clone $startsAt)
                ->addMinutes($activity->duration_in_minutes);

            $activity->update([
                'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'place_id' => Place::inRandomOrder()->first()?->id,
            ]);

            $users  = collect($users);

            $activity->users()->attach(
                $users->random(rand(1, min(3, $activity->max_participants)))->pluck('id')
            );
        });
        //        select activity_waitlist_entries
    }

    public function proposedToEvent()
    {
        $this->state(fn (array $attributes) => [
            'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
        ]);
        //        select activity_proposals
        //        select activity_proposal_slot
    }

    public function scheduledOnEvent()
    {
        $this->state(fn (array $attributes) => [
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        //        select activity_proposals
        //        select activity_proposal_slot
        //        select activity_user
        //        select activity_waitlist_entries
    }

    public function cancelled()
    {
        $this->state(fn (array $attributes) => [
            'cancelled_at' => fake()->dateTime('now'),
            'cancelled_by' => User::factory(),
            'cancel_reason' => fake()->optional()->text,
        ]);
    }
}
