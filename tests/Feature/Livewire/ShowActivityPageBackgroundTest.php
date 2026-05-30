<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Actions\Seeders\AttachTagMediaFromPublic;
use App\Enums\ActivityLogoSource;
use App\Livewire\Activities\ShowActivity;
use App\Models\Activity;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ShowActivityPageBackgroundTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_blurred_page_background_with_resolved_cover_picture(): void
    {
        $tag = Tag::factory()->create();
        $fixturePath = 'images/tag-game/activity-show-background.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));
        app(AttachTagMediaFromPublic::class)($tag, [$fixturePath]);
        $media = $tag->refresh()->getFirstMedia('images');
        $this->assertNotNull($media);

        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Tag,
            'tag_media_id' => $media->id,
        ]);

        $html = Livewire::test(ShowActivity::class, ['activity' => $activity])->html();

        $this->assertStringContainsString('data-ui="activity-show-page-background"', $html);
        $this->assertStringContainsString('blur-md', $html);
        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
    }
}
