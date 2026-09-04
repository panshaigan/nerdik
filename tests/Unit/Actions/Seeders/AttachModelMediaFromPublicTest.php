<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Seeders;

use App\Actions\Seeders\AttachModelMediaFromPublic;
use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\Support\CreatesPublicImageFixture;
use Tests\TestCase;

final class AttachModelMediaFromPublicTest extends TestCase
{
    use CreatesPublicImageFixture;
    use RefreshDatabase;

    #[Test]
    public function it_queues_media_conversions_when_enabled(): void
    {
        Queue::fake();

        config([
            'media.queue_conversions' => true,
            'media-library.queue_conversions_by_default' => true,
            'media-library.queue_connection_name' => 'database',
        ]);

        $tag = Tag::factory()->create([
            'tag_category_id' => TagCategory::factory()->create(['key' => TagCategory::KEY_GAME])->id,
        ]);

        $fixture = $this->createPublicImageFixture('images/testing/queue-attach-test.jpg');

        app(AttachModelMediaFromPublic::class)($tag, [$fixture]);

        Queue::assertPushed(PerformConversionsJob::class);
    }

    #[Test]
    public function it_does_not_queue_media_conversions_when_disabled(): void
    {
        Queue::fake();

        config([
            'media.queue_conversions' => false,
            'media-library.queue_conversions_by_default' => false,
        ]);

        $tag = Tag::factory()->create([
            'tag_category_id' => TagCategory::factory()->create(['key' => TagCategory::KEY_GAME])->id,
        ]);

        $fixture = $this->createPublicImageFixture('images/testing/sync-attach-test.jpg');

        app(AttachModelMediaFromPublic::class)($tag, [$fixture]);

        Queue::assertNothingPushed();
    }
}
