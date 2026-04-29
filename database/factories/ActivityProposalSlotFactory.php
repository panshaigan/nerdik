<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityProposalSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ActivityProposalSlot>
 */
final class ActivityProposalSlotFactory extends Factory
{
    /**
    * The name of the factory's corresponding model.
    *
    * @var string
    */
    protected $model = ActivityProposalSlot::class;

    /**
    * Define the model's default state.
    *
    * @return array
    */
    #[\Override]
    public function definition(): array
    {
        return [
            'activity_proposal_id' => \App\Models\ActivityProposal::factory(),
            'slot_id' => \App\Models\Slot::factory(),
        ];
    }
}
