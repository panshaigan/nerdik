<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Seeders;

use App\Actions\Seeders\AttachTagMediaFromPublic;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsResponsiveMedia;
use Tests\Support\CreatesPublicImageFixture;
use Tests\TestCase;

final class AttachTagMediaFromPublicTest extends TestCase
{
    use AssertsResponsiveMedia;
    use CreatesPublicImageFixture;
    use RefreshDatabase;

    private AttachTagMediaFromPublic $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(AttachTagMediaFromPublic::class);
    }

    #[Test]
    public function it_attaches_media_with_conversions_and_responsive_images(): void
    {
        config(['media.testing.conversion_formats' => ['avif', 'webp']]);

        $tag = Tag::factory()->create();

        $fixturePath = $this->createPublicImageFixture('images/testing/fixture-seed.jpg');

        ($this->action)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');

        $this->assertNotNull($media);
        $this->assertSame($fixturePath, $media->getCustomProperty('seed_source'));
        $this->assertMediaHasResponsiveConversions($media);
    }

    #[Test]
    public function it_is_idempotent_for_the_same_seed_source(): void
    {
        $tag = Tag::factory()->create();

        $fixturePath = $this->createPublicImageFixture('images/testing/fixture-idempotent.jpg');

        ($this->action)($tag, [$fixturePath]);
        ($this->action)($tag, [$fixturePath]);

        $this->assertCount(1, $tag->refresh()->getMedia('images'));
    }
}
