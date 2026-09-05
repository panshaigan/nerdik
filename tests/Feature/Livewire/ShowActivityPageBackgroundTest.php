<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\ActivityLogoSource;
use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class ShowActivityPageBackgroundTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    #[Test]
    public function it_renders_blurred_page_background_with_resolved_cover_picture(): void
    {
        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag, 'tests/fixtures/activity-show-background.jpg');

        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Tag,
            'tag_media_id' => $media->id,
        ]);

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])->html();

        $this->assertStringContainsString('data-ui="activity-show-page-background"', $html);
        $this->assertStringContainsString('h-lvh', $html);
        $this->assertStringContainsString('blur-md', $html);
        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
    }
}
