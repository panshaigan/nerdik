<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Events;

use App\Models\ActivityType;
use App\Models\Slot;
use App\Support\Events\EventProposalTypeCopy;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventProposalTypeCopyTest extends TestCase
{
    private EventProposalTypeCopy $copy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->copy = new EventProposalTypeCopy;
    }

    #[Test]
    public function it_returns_fallback_when_there_are_no_free_slots(): void
    {
        $phrase = $this->copy->typesPhraseForFreeSlots(collect());

        $this->assertSame(__('ui.activities.propose_noun_fallback'), $phrase);
    }

    #[Test]
    public function it_returns_fallback_when_a_free_slot_accepts_any_type(): void
    {
        $slot = new Slot;
        $slot->setRelation('activityTypes', collect());

        $phrase = $this->copy->typesPhraseForFreeSlots(collect([$slot]));

        $this->assertSame(__('ui.activities.propose_noun_fallback'), $phrase);
    }

    #[Test]
    public function it_uses_rpg_propose_noun_for_rpg_only_slots(): void
    {
        $slot = $this->slotWithTypeSlugs(['rpg']);

        $phrase = $this->copy->typesPhraseForFreeSlots(collect([$slot]));

        $this->assertSame(__('ui.activities.propose_nouns.rpg'), $phrase);
    }

    #[Test]
    public function it_deduplicates_board_and_card_into_one_game_noun(): void
    {
        $slot = $this->slotWithTypeSlugs(['board', 'card']);

        $phrase = $this->copy->typesPhraseForFreeSlots(collect([$slot]));

        $this->assertSame(__('ui.activities.propose_nouns.board'), $phrase);
    }

    #[Test]
    public function it_joins_three_distinct_nouns(): void
    {
        $slots = collect([
            $this->slotWithTypeSlugs(['rpg']),
            $this->slotWithTypeSlugs(['board']),
            $this->slotWithTypeSlugs(['workshop']),
        ]);

        $phrase = $this->copy->typesPhraseForFreeSlots($slots);

        $this->assertSame(
            __('ui.events.propose_types_triple', [
                'first' => __('ui.activities.propose_nouns.rpg'),
                'second' => __('ui.activities.propose_nouns.board'),
                'third' => __('ui.activities.propose_nouns.workshop'),
            ]),
            $phrase,
        );
    }

    #[Test]
    public function it_lists_three_nouns_and_overflow_when_more_types_exist(): void
    {
        $slots = collect([
            $this->slotWithTypeSlugs(['rpg']),
            $this->slotWithTypeSlugs(['board']),
            $this->slotWithTypeSlugs(['workshop']),
            $this->slotWithTypeSlugs(['lecture']),
        ]);

        $phrase = $this->copy->typesPhraseForFreeSlots($slots);

        $expected = trim(__('ui.events.propose_types_triple', [
            'first' => __('ui.activities.propose_nouns.rpg'),
            'second' => __('ui.activities.propose_nouns.board'),
            'third' => __('ui.activities.propose_nouns.lecture'),
        ]).' '.__('ui.events.propose_types_overflow'));

        $this->assertSame($expected, $phrase);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function slotWithTypeSlugs(array $slugs): Slot
    {
        $slot = new Slot;
        $types = Collection::make($slugs)->map(
            fn (string $slug): ActivityType => new ActivityType(['slug' => $slug]),
        );
        $slot->setRelation('activityTypes', $types);

        return $slot;
    }
}
