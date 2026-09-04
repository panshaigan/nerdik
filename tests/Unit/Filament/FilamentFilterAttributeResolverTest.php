<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Tag;
use App\Models\TagRelation;
use App\Support\Filament\FilamentFilterAttributeResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FilamentFilterAttributeResolverTest extends TestCase
{
    #[Test]
    public function it_maps_activity_type_relationship_name_to_foreign_key(): void
    {
        $resolved = FilamentFilterAttributeResolver::resolveBelongsToForeignKey(
            new Activity,
            'activityType',
        );

        $this->assertSame('activity_type_id', $resolved);
    }

    #[Test]
    public function it_maps_tag_category_relationship_name_to_foreign_key(): void
    {
        $resolved = FilamentFilterAttributeResolver::resolveBelongsToForeignKey(
            new Tag,
            'tagCategory',
        );

        $this->assertSame('tag_category_id', $resolved);
    }

    #[Test]
    public function it_maps_related_tag_relationship_name_to_foreign_key(): void
    {
        $resolved = FilamentFilterAttributeResolver::resolveBelongsToForeignKey(
            new TagRelation,
            'relatedTag',
        );

        $this->assertSame('related_tag_id', $resolved);
    }

    #[Test]
    public function it_maps_accepted_slot_relationship_name_to_foreign_key(): void
    {
        $resolved = FilamentFilterAttributeResolver::resolveBelongsToForeignKey(
            new ActivityProposal,
            'acceptedSlot',
        );

        $this->assertSame('accepted_slot_id', $resolved);
    }

    #[Test]
    public function it_keeps_physical_columns_as_is(): void
    {
        $resolved = FilamentFilterAttributeResolver::resolveBelongsToForeignKey(
            new Activity,
            'activity_type_id',
        );

        $this->assertSame('activity_type_id', $resolved);
    }
}
