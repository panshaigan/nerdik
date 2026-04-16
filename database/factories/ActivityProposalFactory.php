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
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'event_id' => Event::factory(),
            'status' => 'pending',
            'created_by' => User::factory(),
        ];
    }
}
