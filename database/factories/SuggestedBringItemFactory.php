<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\SuggestedBringItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestedBringItem>
 */
final class SuggestedBringItemFactory extends Factory
{
    protected $model = SuggestedBringItem::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'bringable_type' => 'activity',
            'bringable_id' => Activity::factory(),
            'label' => fake()->words(3, true),
            'sort_order' => 0,
            'host_provides' => false,
        ];
    }

    public function forActivity(Activity $activity): static
    {
        return $this->state(fn (): array => [
            'bringable_type' => 'activity',
            'bringable_id' => $activity->id,
        ]);
    }
}
