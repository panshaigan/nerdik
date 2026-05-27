<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityProposal;
use App\Models\ActivityProposalSlot;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityProposalSlot>
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
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'activity_proposal_id' => ActivityProposal::factory(),
            'slot_id' => Slot::factory(),
        ];
    }
}
