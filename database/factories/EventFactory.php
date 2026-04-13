<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

use function fake;

/**
 * @extends Factory<\App\Models\Event>
 */
final class EventFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Event::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        $name = fake()->name;

        $startsAt = fake()->dateTimeBetween('+1 week', '+6 months')
            ->setTime(fake()->numberBetween(9, 17), 0, 0);

        $startsAt = \Carbon\Carbon::instance($startsAt);

        $durationDays = fake()->numberBetween(0, 2);

        $endsAt = (clone $startsAt)
            ->addDays($durationDays)
            ->setTime(fake()->numberBetween(18, 23), fake()->numberBetween(0, 59));

        return [
            'name' => $name,
            'organization_id' => Organization::factory(),
            'is_public' => fake()->boolean(),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->text,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => 1,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => 0,
        ]);
    }

    public function withSameCreatorAsOrganization(): static
    {
        return $this->afterCreating(function (Event $event) {
            if ($event->organization?->created_by) {
                $event->update([
                    'created_by' => $event->organization->created_by,
                ]);
            }
        });
    }
}
