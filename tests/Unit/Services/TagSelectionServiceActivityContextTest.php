<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\TagContext;
use App\Services\TagSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TagSelectionServiceActivityContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tag_without_contexts_is_always_eligible_for_suggestions(): void
    {
        $tag = Tag::factory()->create();

        $service = new TagSelectionService;

        $this->assertTrue($service->isTagEligibleForActivityTypeSuggestions($tag, null));
        $this->assertTrue($service->isTagEligibleForActivityTypeSuggestions($tag, 0));
        $this->assertTrue($service->isTagEligibleForActivityTypeSuggestions($tag, 99));
    }

    #[Test]
    public function tag_with_activity_type_contexts_is_ineligible_when_no_activity_type_selected(): void
    {
        $tag = Tag::factory()->create();
        $type = ActivityType::factory()->create();

        TagContext::factory()->create([
            'tag_id' => $tag->id,
            'context_type' => TagContext::CONTEXT_TYPE_ACTIVITY_TYPE,
            'context_id' => $type->id,
        ]);

        $tag->refresh()->load('contexts');
        $service = new TagSelectionService;

        $this->assertFalse($service->isTagEligibleForActivityTypeSuggestions($tag, null));
        $this->assertFalse($service->isTagEligibleForActivityTypeSuggestions($tag, 0));
    }

    #[Test]
    public function tag_with_activity_type_contexts_is_eligible_only_for_matching_type(): void
    {
        $tag = Tag::factory()->create();
        $typeA = ActivityType::factory()->create();
        $typeB = ActivityType::factory()->create();

        TagContext::factory()->create([
            'tag_id' => $tag->id,
            'context_type' => TagContext::CONTEXT_TYPE_ACTIVITY_TYPE,
            'context_id' => $typeA->id,
        ]);

        $tag->refresh()->load('contexts');
        $service = new TagSelectionService;

        $this->assertTrue($service->isTagEligibleForActivityTypeSuggestions($tag, (int) $typeA->id));
        $this->assertFalse($service->isTagEligibleForActivityTypeSuggestions($tag, (int) $typeB->id));
    }

    #[Test]
    public function tags_payload_includes_context_activity_type_ids(): void
    {
        $tag = Tag::factory()->create();
        $type = ActivityType::factory()->create();

        TagContext::factory()->create([
            'tag_id' => $tag->id,
            'context_type' => TagContext::CONTEXT_TYPE_ACTIVITY_TYPE,
            'context_id' => $type->id,
        ]);

        $service = new TagSelectionService;
        $payload = $service->tagsPayloadForActivityPicker(
            Tag::query()->forActivityFormPicker()->whereKey($tag->id)->get(),
            'en'
        );

        $this->assertCount(1, $payload);
        $this->assertSame([(int) $type->id], $payload[0]['context_activity_type_ids']);
        $this->assertArrayHasKey('popularity_score', $payload[0]);
        $this->assertSame((int) ($tag->popularity_score ?? 0), $payload[0]['popularity_score']);
    }
}
