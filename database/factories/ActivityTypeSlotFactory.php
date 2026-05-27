<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityType;
use App\Models\ActivityTypeSlot;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityTypeSlot>
 */
final class ActivityTypeSlotFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ActivityTypeSlot::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'slot_id' => Slot::factory(),
            'activity_type_id' => ActivityType::factory(),
        ];
    }
}
