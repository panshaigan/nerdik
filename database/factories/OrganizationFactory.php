<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Organization>
 */
final class OrganizationFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = Organization::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'logo_path' => fake()->optional()->word,
            'slug' => fake()->slug,
            'description' => fake()->optional()->text,
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'deleted_by' => \App\Models\User::factory(),
        ];
    }
}
