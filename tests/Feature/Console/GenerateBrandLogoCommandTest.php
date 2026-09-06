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

    #[Test]
    public function command_generates_configured_brand_logo_variants(): void
    {
        $this->isolatedOutputDir = 'images/app/brand/test-output-'.getmypid();
        config(['media.brand_logo.output_dir' => $this->isolatedOutputDir]);

        $this->artisan('app:generate-brand-logo')
            ->assertSuccessful();

        $absoluteOutputDir = public_path($this->isolatedOutputDir);

        foreach ([40, 48, 64, 80, 96, 128, 160, 192] as $width) {
            $this->assertFileExists("{$absoluteOutputDir}/{$width}w.webp");
        }

        $this->assertFileDoesNotExist("{$absoluteOutputDir}/256w.webp");

        $manifest = json_decode(
            File::get("{$absoluteOutputDir}/manifest.json"),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(1139, $manifest['width']);
        $this->assertSame(1054, $manifest['height']);
        $this->assertTrue($manifest['trimmed']);
        $this->assertCount(8, $manifest['variants']['webp']);

        $smallestBytes = $manifest['variants']['webp'][0]['bytes'];
        $largestBytes = $manifest['variants']['webp'][7]['bytes'];

        $this->assertLessThan($largestBytes, $smallestBytes);

        $largestPath = public_path($manifest['variants']['webp'][7]['path']);
        $largestSize = getimagesize($largestPath);
        $this->assertNotFalse($largestSize);
        $this->assertSame(192, $largestSize[0]);
        $this->assertLessThan(192, $largestSize[1]);
    }

    protected function tearDown(): void
    {
        if ($this->isolatedOutputDir !== null) {
            File::deleteDirectory(public_path($this->isolatedOutputDir));
            $this->isolatedOutputDir = null;
        }

        parent::tearDown();
    }
}
