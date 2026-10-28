<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\SuggestedBringItem;
use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ShowActivitySuggestedBringTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_suggested_bring_with_not_a_gate_copy_and_host_provides(): void
    {
        $rpg = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $typeItem = SuggestedBringItem::factory()
            ->state([
                'bringable_type' => 'activity_type',
                'bringable_id' => $rpg->id,
            ])
            ->withTranslations(['en' => 'Paper'])
            ->create();

        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $gameTag = Tag::factory()->for($gameCategory, 'tagCategory')->create();

        $activity = Activity::factory()->selfHosted()->create([
            'activity_type_id' => $rpg->id,
        ]);
        $activity->tags()->attach($gameTag->id);
        $activity->providedSuggestedBringItems()->attach($typeItem->id);

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])->html();

        $this->assertStringContainsString('data-ui="activity-show-suggested-bring"', $html);
        $this->assertStringContainsString(__('ui.activities.suggested_bring.optional_label'), $html);
        $this->assertStringContainsString(__('ui.activities.suggested_bring.not_a_gate'), $html);
        $this->assertStringContainsString('Paper', $html);
        $this->assertStringContainsString(__('ui.activities.suggested_bring.host_provides'), $html);
    }

    #[Test]
    public function it_hides_suggested_bring_when_there_are_no_rows(): void
    {
        $activity = Activity::factory()->selfHosted()->create();

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])->html();

        $this->assertStringNotContainsString('data-ui="activity-show-suggested-bring"', $html);
    }
}
