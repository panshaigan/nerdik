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
    const SAMPLE_PASSWORD = 'password';

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'nickname' => fake()->unique()->userName,
            'email' => fake()->safeEmail,
            'password' => Hash::make(self::SAMPLE_PASSWORD),
            'google_id' => null,
            'avatar_path' => null,
            'discord_handle' => null,
            'current_location' => null,
            'timezone' => null,
            'is_admin' => 0,
            'is_event_organizer' => 0,
            'languages' => null,
            'notify_email_proposal_updates' => fake()->boolean(),
            'notify_email_waitlist_promoted' => fake()->boolean(),
            'remember_token' => Str::random(10),
            'email_verified_at' => fake()->dateTime(),
        ];
    }

    public function admin(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => 1,
        ]);
    }

    public function organizer(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_event_organizer' => 1,
        ]);
    }

    public function specific(string $name, string $nickname, string $email): self
    {
        return $this->state(fn (array $attributes) => [
            'nickname' => $nickname,
            'name' => $name,
            'email' => $email,
        ]);
    }

    public function unverified(): self
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
