<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tag;
use App\Models\TagRelation;
use App\Services\TagSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TagSelectionServiceDescendantExpansionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function expand_tag_ids_to_descendants_includes_child_not_parent(): void
    {
        $parent = Tag::factory()->create();
        $mid = Tag::factory()->create();
        $child = Tag::factory()->create();

        TagRelation::query()->create(['tag_id' => $child->id, 'related_tag_id' => $mid->id]);
        TagRelation::query()->create(['tag_id' => $mid->id, 'related_tag_id' => $parent->id]);

        $service = new TagSelectionService;

        $expanded = $service->expandTagIdsToDescendants([(int) $mid->id]);

        $this->assertContains((int) $mid->id, $expanded);
        $this->assertContains((int) $child->id, $expanded);
        $this->assertNotContains((int) $parent->id, $expanded);
    }

    #[Test]
    public function expand_tag_ids_to_descendants_on_leaf_returns_only_self(): void
    {
        $parent = Tag::factory()->create();
        $child = Tag::factory()->create();

        TagRelation::query()->create(['tag_id' => $child->id, 'related_tag_id' => $parent->id]);

        $service = new TagSelectionService;

        $expanded = $service->expandTagIdsToDescendants([(int) $child->id]);

        $this->assertSame([(int) $child->id], $expanded);
    }

    #[Test]
    public function expand_tag_ids_to_descendants_on_parent_includes_full_chain(): void
    {
        $parent = Tag::factory()->create();
        $mid = Tag::factory()->create();
        $child = Tag::factory()->create();

        TagRelation::query()->create(['tag_id' => $child->id, 'related_tag_id' => $mid->id]);
        TagRelation::query()->create(['tag_id' => $mid->id, 'related_tag_id' => $parent->id]);

        $service = new TagSelectionService;

        $expanded = $service->expandTagIdsToDescendants([(int) $parent->id]);

        $this->assertEqualsCanonicalizing(
            [(int) $parent->id, (int) $mid->id, (int) $child->id],
            $expanded
        );
    }
}
