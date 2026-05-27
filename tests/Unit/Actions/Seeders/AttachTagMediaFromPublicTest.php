<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Seeders;

use App\Actions\Seeders\AttachTagMediaFromPublic;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AttachTagMediaFromPublicTest extends TestCase
{
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
        config([
            'media.test_profile' => 'full',
            'media.responsive_widths' => [128, 256, 384, 512, 768, 1024, 1536],
        ]);

        $tag = Tag::factory()->create();

        $fixturePath = 'images/tag-game/fixture-seed.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        ($this->action)($tag, [$fixturePath]);

        $media = $tag->refresh()->getFirstMedia('images');

        $this->assertNotNull($media);
        $this->assertSame($fixturePath, $media->getCustomProperty('seed_source'));
        $this->assertTrue($media->hasGeneratedConversion('avif'));
        $this->assertTrue($media->hasGeneratedConversion('webp'));
        $this->assertTrue($media->hasGeneratedConversion('jpeg'));
        $this->assertNotEmpty($media->responsive_images);
    }

    #[Test]
    public function it_is_idempotent_for_the_same_seed_source(): void
    {
        $tag = Tag::factory()->create();

        $fixturePath = 'images/tag-game/fixture-idempotent.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixturePath));

        ($this->action)($tag, [$fixturePath]);
        ($this->action)($tag, [$fixturePath]);

        $this->assertCount(1, $tag->refresh()->getMedia('images'));
    }
}
