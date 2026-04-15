<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'nickname' => fake()->unique()->userName,
            'email' => fake()->safeEmail,
            'password' => Hash::make('password'),
            'google_id' => null,
            'avatar_path' => null,
            'discord_handle' => null,
            'current_location' => null,
            'timezone' => null,
            'is_admin' => 0,
            'is_event_organizer' => 0,
            'languages' => null,
            'notify_email_proposal_updates' => fake()->randomNumber(1),
            'notify_email_waitlist_promoted' => fake()->randomNumber(1),
            'remember_token' => Str::random(10),
            'email_verified_at' => fake()->optional()->dateTime(),
        ];
    }

    public function admin(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => 1,
        ]);
    }

    public function eventOrganizer(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_event_organizer' => 1,
        ]);
    }

    public function email(string $email): self
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }

    public function nickname(string $name): self
    {
        return $this->state(fn (array $attributes) => [
            'nickname' => $name,
            'name' => $name,
        ]);
    }
}
