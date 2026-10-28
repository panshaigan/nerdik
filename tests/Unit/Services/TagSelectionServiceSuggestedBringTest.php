<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Tag;
use App\Models\TagCategory;
use App\Services\TagSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TagSelectionServiceSuggestedBringTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_suggested_bring_only_when_creating_a_new_tag(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);

        $service = app(TagSelectionService::class);

        $firstId = $service->findOrCreateTagByLabel('Unique Homebrew', $gameCategory->id, ['d6s']);
        $secondId = $service->findOrCreateTagByLabel('Unique Homebrew', $gameCategory->id, ['ignored line']);

        $this->assertSame($firstId, $secondId);

        $tag = Tag::query()->findOrFail($firstId);
        $labels = $tag->suggestedBringItems()->pluck('label')->all();

        $this->assertSame(['d6s'], $labels);
    }

    #[Test]
    public function resolve_final_tag_ids_attaches_kit_from_new_tags_payload(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $service = app(TagSelectionService::class);

        $ids = $service->resolveFinalTagIds([], [
            [
                'label' => 'Brand New Game',
                'category_id' => $gameCategory->id,
                'suggested_bring' => ['Polyhedral dice'],
            ],
        ]);

        $this->assertCount(1, $ids);
        $tag = Tag::query()->findOrFail($ids[0]);
        $this->assertSame(['Polyhedral dice'], $tag->suggestedBringItems()->pluck('label')->all());
    }
}
