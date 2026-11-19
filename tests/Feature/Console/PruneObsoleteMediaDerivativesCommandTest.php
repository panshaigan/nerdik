<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\Media\PruneObsoleteResponsiveWidths;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class PruneObsoleteMediaDerivativesCommandTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    #[Test]
    public function it_prunes_responsive_urls_and_files_for_obsolete_widths(): void
    {
        config(['media.responsive_widths' => [128, 384, 768, 1536]]);

        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag);

        $directory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);
        $obsoleteFile = $media->file_name;
        $obsoleteFile = preg_replace('/\.[^.]+$/', '', $obsoleteFile).'-sample___webp_256_144.webp';

        Storage::disk($media->disk)->put($directory.$obsoleteFile, 'obsolete');
        $keptFile = preg_replace('/\.[^.]+$/', '', $media->file_name).'-sample___webp_384_216.webp';
        Storage::disk($media->disk)->put($directory.$keptFile, 'kept');

        $media->responsive_images = [
            'webp' => [
                'urls' => [$obsoleteFile, $keptFile],
            ],
        ];
        $media->save();

        $this->artisan('media:prune-obsolete-derivatives', [
            '--force' => true,
            '--skip-conversions' => true,
        ])->assertSuccessful();

        $media->refresh();

        $this->assertTrue(Storage::disk($media->disk)->exists($directory.$keptFile));
        $this->assertFalse(Storage::disk($media->disk)->exists($directory.$obsoleteFile));
        $this->assertSame(
            [$keptFile],
            $media->responsive_images['webp']['urls'] ?? [],
        );
    }

    #[Test]
    public function dry_run_does_not_delete_obsolete_responsive_files(): void
    {
        config(['media.responsive_widths' => [128, 384, 768, 1536]]);

        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag);

        $directory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);
        $obsoleteFile = preg_replace('/\.[^.]+$/', '', $media->file_name).'-sample___webp_512_288.webp';
        Storage::disk($media->disk)->put($directory.$obsoleteFile, 'obsolete');

        $media->responsive_images = [
            'webp' => [
                'urls' => [$obsoleteFile],
            ],
        ];
        $media->save();

        $this->artisan('media:prune-obsolete-derivatives', [
            '--dry-run' => true,
            '--force' => true,
            '--skip-conversions' => true,
        ])->assertSuccessful();

        $this->assertTrue(Storage::disk($media->disk)->exists($directory.$obsoleteFile));
        $this->assertSame(
            [$obsoleteFile],
            $media->refresh()->responsive_images['webp']['urls'] ?? [],
        );
    }

    #[Test]
    public function action_invocation_reports_pruned_urls(): void
    {
        config(['media.responsive_widths' => [384]]);

        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag);

        $obsolete = 'fixture___webp_128_72.webp';
        $directory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);
        Storage::disk($media->disk)->put($directory.$obsolete, 'x');

        $media->responsive_images = [
            'webp' => [
                'urls' => [$obsolete],
            ],
        ];
        $media->save();

        $result = app(PruneObsoleteResponsiveWidths::class)($media, dryRun: false);

        $this->assertSame(1, $result['urls_pruned']);
        $this->assertGreaterThanOrEqual(1, $result['files_removed']);
        $this->assertSame([], $media->refresh()->responsive_images['webp']['urls'] ?? []);
    }
}
