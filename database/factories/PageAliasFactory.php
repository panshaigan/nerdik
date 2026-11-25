<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PageAlias;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageAlias>
 */
final class PageAliasFactory extends Factory
{
    protected $model = PageAlias::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'alias' => fake()->unique()->slug(2),
            'target_path' => '/dashboard',
            'created_by_id' => User::factory(),
            'hits' => 0,
        ];
    }
}
