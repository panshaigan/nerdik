<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\SuggestedBringItem;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Services\ActivitySuggestedBringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivitySuggestedBringServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_merges_type_game_and_extra_rows_in_order(): void
    {
        $rpg = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        SuggestedBringItem::factory()->create([
            'bringable_type' => 'activity_type',
            'bringable_id' => $rpg->id,
            'label' => 'Paper',
            'sort_order' => 0,
        ]);

        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $settingCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_SETTING]);
        $gameTag = Tag::factory()->for($gameCategory, 'tagCategory')->create();
        $settingTag = Tag::factory()->for($settingCategory, 'tagCategory')->create();
        SuggestedBringItem::factory()->create([
            'bringable_type' => 'tag',
            'bringable_id' => $gameTag->id,
            'label' => 'Polyhedral dice',
            'sort_order' => 0,
        ]);
        SuggestedBringItem::factory()->create([
            'bringable_type' => 'tag',
            'bringable_id' => $settingTag->id,
            'label' => 'Should not appear',
            'sort_order' => 0,
        ]);

        $activity = Activity::factory()->selfHosted()->create([
            'activity_type_id' => $rpg->id,
        ]);
        $activity->tags()->attach([$gameTag->id, $settingTag->id]);

        SuggestedBringItem::factory()->forActivity($activity)->create([
            'label' => 'Snacks',
            'sort_order' => 0,
            'host_provides' => true,
        ]);

        $rows = app(ActivitySuggestedBringService::class)->listForActivity($activity);

        $this->assertSame(['Paper', 'Polyhedral dice', 'Snacks'], array_column($rows, 'label'));
        $this->assertSame([true, true, false], array_column($rows, 'inherited'));
        $this->assertFalse($rows[0]['host_provides']);
        $this->assertTrue($rows[2]['host_provides']);
    }

    #[Test]
    public function it_applies_host_provisions_to_inherited_items(): void
    {
        $rpg = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $item = SuggestedBringItem::factory()->create([
            'bringable_type' => 'activity_type',
            'bringable_id' => $rpg->id,
            'label' => 'Pencil',
            'sort_order' => 0,
        ]);

        $activity = Activity::factory()->selfHosted()->create([
            'activity_type_id' => $rpg->id,
        ]);
        $activity->providedSuggestedBringItems()->attach($item->id);

        $rows = app(ActivitySuggestedBringService::class)->listForActivity($activity);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['host_provides']);
    }

    #[Test]
    public function sync_activity_kit_persists_extras_and_provisions(): void
    {
        $rpg = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $inherited = SuggestedBringItem::factory()->create([
            'bringable_type' => 'activity_type',
            'bringable_id' => $rpg->id,
            'label' => 'Paper',
            'sort_order' => 0,
        ]);

        $activity = Activity::factory()->selfHosted()->create([
            'activity_type_id' => $rpg->id,
        ]);

        app(ActivitySuggestedBringService::class)->syncActivityKit(
            $activity,
            [(int) $inherited->id],
            [
                ['label' => 'Character sheet', 'host_provides' => false],
            ],
        );

        $activity->refresh();
        $rows = app(ActivitySuggestedBringService::class)->listForActivity($activity);

        $this->assertTrue($rows[0]['host_provides']);
        $this->assertSame('Character sheet', $rows[1]['label']);
        $this->assertFalse($rows[1]['inherited']);
    }
}
