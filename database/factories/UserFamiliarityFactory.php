<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FamiliarityLevel;
use App\Models\ActivityType;
use App\Models\User;
use App\Models\UserFamiliarity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFamiliarity>
 */
final class UserFamiliarityFactory extends Factory
{
    protected $model = UserFamiliarity::class;

    public function definition(): array
    {
        $activityType = ActivityType::query()->first() ?? ActivityType::factory()->create();

        return [
            'user_id' => User::factory(),
            'subject_type' => (new ActivityType)->getMorphClass(),
            'subject_id' => $activityType->id,
            'level' => fake()->randomElement(FamiliarityLevel::cases()),
        ];
    }
}
