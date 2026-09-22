<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('brand-logo-mutation')]
final class GenerateBrandLogoCommandTest extends TestCase
{
    private ?string $isolatedOutputDir = null;

    private ?string $isolatedIconsDir = null;

    #[Test]
    public function command_generates_configured_brand_logo_variants(): void
    {
        $this->isolatedOutputDir = 'images/app/brand/test-output-'.getmypid();
        $this->isolatedIconsDir = $this->isolatedOutputDir.'/icons';

        config([
            'media.brand_logo.output_dir' => $this->isolatedOutputDir,
            'media.brand_logo.icons.favicon_ico' => $this->isolatedIconsDir.'/favicon.ico',
            'media.brand_logo.icons.favicon_svg' => $this->isolatedIconsDir.'/favicon.svg',
            'media.brand_logo.icons.apple_touch_icon' => $this->isolatedIconsDir.'/apple-touch-icon.png',
        ]);

        $sourcePath = base_path((string) config('media.brand_logo.source'));
        $sourceSize = getimagesize($sourcePath);
        $this->assertNotFalse($sourceSize);

        /** @var list<int> $widths */
        $widths = config('media.brand_logo.widths', []);

        $this->artisan('app:generate-brand-logo')
            ->assertSuccessful();

        $absoluteOutputDir = public_path($this->isolatedOutputDir);

        foreach ($widths as $width) {
            $this->assertFileExists("{$absoluteOutputDir}/{$width}w.webp");
        }

        $this->assertFileDoesNotExist("{$absoluteOutputDir}/256w.webp");

        $manifest = json_decode(
            File::get("{$absoluteOutputDir}/manifest.json"),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertGreaterThan(0, $manifest['width']);
        $this->assertGreaterThan(0, $manifest['height']);
        $this->assertLessThanOrEqual($sourceSize[0], $manifest['width']);
        $this->assertLessThanOrEqual($sourceSize[1], $manifest['height']);
        $this->assertTrue($manifest['trimmed']);
        $this->assertCount(count($widths), $manifest['variants']['webp']);

        $smallestBytes = $manifest['variants']['webp'][0]['bytes'];
        $largestBytes = $manifest['variants']['webp'][array_key_last($manifest['variants']['webp'])]['bytes'];

        $this->assertLessThan($largestBytes, $smallestBytes);

        $largestEntry = $manifest['variants']['webp'][array_key_last($manifest['variants']['webp'])];
        $largestPath = public_path($largestEntry['path']);
        $largestSize = getimagesize($largestPath);
        $this->assertNotFalse($largestSize);
        $this->assertSame($largestEntry['width'], $largestSize[0]);
        $this->assertSame(
            (int) round($largestEntry['width'] * $manifest['height'] / $manifest['width']),
            $largestSize[1],
        );

        $iconsDir = public_path($this->isolatedIconsDir);
        $this->assertFileExists("{$iconsDir}/favicon.ico");
        $this->assertGreaterThan(0, (int) filesize("{$iconsDir}/favicon.ico"));
        $this->assertFileExists("{$iconsDir}/favicon.svg");
        $this->assertStringContainsString('data:image/png;base64,', File::get("{$iconsDir}/favicon.svg"));
        $this->assertFileExists("{$iconsDir}/apple-touch-icon.png");

        $appleSize = getimagesize("{$iconsDir}/apple-touch-icon.png");
        $this->assertNotFalse($appleSize);
        $appleTouchSize = (int) config('media.brand_logo.icons.apple_touch_size');
        $this->assertSame($appleTouchSize, $appleSize[0]);
        $this->assertSame($appleTouchSize, $appleSize[1]);
    }

    protected function tearDown(): void
    {
        if ($this->isolatedOutputDir !== null) {
            File::deleteDirectory(public_path($this->isolatedOutputDir));
            $this->isolatedOutputDir = null;
            $this->isolatedIconsDir = null;
        }

        parent::tearDown();
    }
}
