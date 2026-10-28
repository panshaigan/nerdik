<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SuggestedBringItem;
use App\Models\SuggestedBringItemTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestedBringItemTranslation>
 */
final class SuggestedBringItemTranslationFactory extends Factory
{
    protected $model = SuggestedBringItemTranslation::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'suggested_bring_item_id' => SuggestedBringItem::factory(),
            'locale' => 'en',
            'label' => fake()->words(3, true),
        ];
    }
}
