<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityWaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ActivityWaitlistEntry>
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
    *
    * @return array
    */
    #[\Override]
    public function definition(): array
    {
        return [
            'activity_id' => \App\Models\Activity::factory(),
            'user_id' => \App\Models\User::factory(),
            'position' => fake()->optional()->randomNumber(1),
        ];
    }
}
