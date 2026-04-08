<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\User>
 */
final class UserFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = User::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'nickname' => fake()->word,
            'email' => fake()->safeEmail,
            'is_admin' => fake()->randomNumber(1),
            'email_verified_at' => fake()->optional()->datetime(),
            'password' => bcrypt(fake()->password),
            'google_id' => fake()->optional()->word,
            'avatar_path' => fake()->optional()->word,
            'discord_handle' => fake()->optional()->word,
            'current_location' => fake()->optional()->word,
            'timezone' => fake()->optional()->word,
            'languages' => fake()->optional()->word,
            'notify_email_proposal_updates' => fake()->randomNumber(1),
            'notify_email_waitlist_promoted' => fake()->randomNumber(1),
            'remember_token' => Str::random(10),
        ];
    }
}
