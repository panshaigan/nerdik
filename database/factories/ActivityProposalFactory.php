<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ActivityProposal>
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
    public function definition(): array
    {
        return [
            'activity_id' => \App\Models\Activity::factory(),
            'event_id' => \App\Models\Event::factory(),
            'accepted_slot_id' => \App\Models\Slot::factory(),
            'status' => fake()->word,
            'preferred_start_time' => fake()->optional()->dateTime(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'deleted_by' => \App\Models\User::factory(),
        ];
    }
}
