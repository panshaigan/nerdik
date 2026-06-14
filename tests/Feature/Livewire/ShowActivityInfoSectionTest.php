<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ShowActivityInfoSectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_glass_info_section_with_badges_and_stats(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $triggerCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_TRIGGER]);
        $gameTag = Tag::factory()->for($gameCategory, 'tagCategory')->create();
        $triggerTag = Tag::factory()->for($triggerCategory, 'tagCategory')->create();
        TagTranslation::factory()->create(['tag_id' => $gameTag->id, 'locale' => 'en', 'label' => 'Blades in the Dark']);
        TagTranslation::factory()->create(['tag_id' => $triggerTag->id, 'locale' => 'en', 'label' => 'Mental Illness']);

        $activity = Activity::factory()->create([
            'max_participants' => 4,
        ]);
        $activity->tags()->attach([$gameTag->id, $triggerTag->id]);

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])->html();

        $this->assertStringContainsString('data-ui="activity-show-info-section"', $html);
        $this->assertStringContainsString('px-4', $html);
        $this->assertStringContainsString('sm:px-6', $html);
        $this->assertStringContainsString('lg:px-8', $html);
        $this->assertStringContainsString('ui-activity-show-info-badges', $html);
        $this->assertStringContainsString('ui-activity-show-info-panel', $html);
        $this->assertStringContainsString('data-ui="activity-show-badge-group"', $html);
        $this->assertStringContainsString('data-ui="activity-show-participants-stat"', $html);
        $this->assertStringContainsString('data-ui="activity-show-interested-stat"', $html);
        $this->assertStringContainsString('ui-activity-show-stat', $html);
        $this->assertStringContainsString(__('ui.interests.interested_in_short'), $html);
        $this->assertStringContainsString('0/4', $html);
        $this->assertStringContainsString('Blades in the Dark', $html);
        $this->assertStringContainsString('Mental Illness', $html);
        $this->assertStringContainsString('bg-base-100/35', $html);
    }
}
