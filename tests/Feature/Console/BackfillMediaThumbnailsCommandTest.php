<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\BackfillMediaThumbnailsCommand;
use App\Console\Commands\SeedTagImagesCommand;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class BackfillMediaThumbnailsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function regenerate_options_include_force_for_production(): void
    {
        $this->assertTrue(BackfillMediaThumbnailsCommand::REGENERATE_OPTIONS['--force']);
        $this->assertTrue(BackfillMediaThumbnailsCommand::REGENERATE_OPTIONS['--only-missing']);
        $this->assertTrue(BackfillMediaThumbnailsCommand::REGENERATE_OPTIONS['--with-responsive-images']);
        $this->assertTrue(BackfillMediaThumbnailsCommand::REGENERATE_OPTIONS['--no-interaction']);
        $this->assertStringContainsString('--force', BackfillMediaThumbnailsCommand::REGENERATE_COMMAND);
        $this->assertArrayNotHasKey('--only-missing', BackfillMediaThumbnailsCommand::REENCODE_OPTIONS);
        $this->assertTrue(BackfillMediaThumbnailsCommand::REENCODE_OPTIONS['--force']);
        $this->assertSame(
            BackfillMediaThumbnailsCommand::REGENERATE_COMMAND,
            SeedTagImagesCommand::REGENERATE_MISSING_COMMAND,
        );
    }

    #[Test]
    public function conversion_qualities_keep_text_readable_on_listing_cards(): void
    {
        $this->assertGreaterThanOrEqual(70, (int) config('media.conversion_qualities.avif'));
        $this->assertGreaterThanOrEqual(90, (int) config('media.conversion_qualities.webp'));
        $this->assertGreaterThanOrEqual(90, (int) config('media.conversion_qualities.jpeg'));
        $this->assertGreaterThanOrEqual(768, (int) config('media.presets.listing_card.max_srcset_width'));
    }

    #[Test]
    public function reencode_option_regenerates_without_only_missing(): void
    {
        $this->artisan('media:backfill-thumbnails', ['--reencode' => true])
            ->expectsOutputToContain('Running: php artisan '.BackfillMediaThumbnailsCommand::REENCODE_COMMAND.' (reencode)')
            ->expectsOutputToContain('media:backfill-thumbnails --reencode')
            ->assertSuccessful();
    }

    #[Test]
    public function command_reports_missing_derivatives_and_completes(): void
    {
        $tag = Tag::factory()->create();

        Media::query()->create([
            'model_type' => Tag::class,
            'model_id' => $tag->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => 'missing-derivatives',
            'file_name' => 'missing-derivatives.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        Media::query()->create([
            'model_type' => Tag::class,
            'model_id' => $tag->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'images',
            'name' => 'has-derivatives',
            'file_name' => 'has-derivatives.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => ['webp' => true, 'jpeg' => true],
            'responsive_images' => ['webp' => ['urls' => [], 'base64svg' => '']],
            'order_column' => 2,
        ]);

        $this->artisan('media:backfill-thumbnails')
            ->expectsOutputToContain('Media thumbnail status (before):')
            ->expectsOutputToContain('total=2 empty_conversions=1 empty_responsive=1')
            ->expectsOutputToContain('Running: php artisan '.BackfillMediaThumbnailsCommand::REGENERATE_COMMAND)
            ->expectsOutputToContain('Media thumbnail status (after):')
            ->expectsOutputToContain('./scripts/compose-exec.sh prod exec app php artisan media:backfill-thumbnails')
            ->assertSuccessful();
    }
}
