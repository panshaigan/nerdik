<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityWaitlistEntry>
 */
final class ActivityWaitlistEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ActivityWaitlistEntry::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'user_id' => User::factory(),
            'position' => fake()->optional()->randomNumber(1),
        ];
    }
}
