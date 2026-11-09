<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GroupVisibility;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
final class GroupFactory extends Factory
{
    protected $model = Group::class;

    #[\Override]
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'visibility' => GroupVisibility::Private,
            'created_by' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => GroupVisibility::Public,
        ]);
    }

    public function withOwnerMember(): static
    {
        return $this->afterCreating(function (Group $group): void {
            if ($group->created_by === null) {
                return;
            }

            $group->users()->syncWithoutDetaching([(int) $group->created_by]);
        });
    }
}
