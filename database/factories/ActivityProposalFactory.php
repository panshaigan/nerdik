<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityProposal>
 */
final class ActivityProposalFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = ActivityProposal::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    #[\Override]
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'event_id' => Event::factory(),
            'status' => 'pending',
            'created_by' => User::factory(),
        ];
    }

    public function alignWithActivity(Activity $activity): self
    {
        if ($activity->hosting_mode === Activity::HOSTING_MODE_PROPOSED_TO_EVENT) {
            return $this;
        }

        if ($activity->hosting_mode === Activity::HOSTING_MODE_SCHEDULED_ON_EVENT) {

            return $this->afterCreating(function (ActivityProposal $proposal) use ($activity) {
                foreach ($proposal->event->slots as $slot) {
                    if ($slot->activityTypes->contains($activity->activityType)) {
                        $proposal->update([
                            'accepted_slot_id' => $slot->id,
                        ]);
                        $slot->update([
                            'activity_id' => $proposal->activity->id,
                        ]);
                        break;
                    }
                }
            });
        }

        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
        ]);
    }
}
