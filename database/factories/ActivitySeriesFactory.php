<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivitySeries;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ActivitySeries>
 */
final class ActivitySeriesFactory extends Factory
{
    protected $model = ActivitySeries::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
