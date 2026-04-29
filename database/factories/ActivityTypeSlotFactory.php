<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityTypeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ActivityTypeSlot>
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
    *
    * @return array
    */
    #[\Override]
    public function definition(): array
    {
        return [
            'slot_id' => \App\Models\Slot::factory(),
            'activity_type_id' => \App\Models\ActivityType::factory(),
        ];
    }
}
