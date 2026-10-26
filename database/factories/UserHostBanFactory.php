<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserHostBan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserHostBan>
 */
final class UserHostBanFactory extends Factory
{
    protected $model = UserHostBan::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'host_user_id' => User::factory(),
            'banned_user_id' => User::factory(),
        ];
    }
}
