<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Seeders;

use App\Actions\Seeders\OptimizeSeederTagImages;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OptimizeSeederTagImagesTest extends TestCase
{
    private string $libraryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->libraryDirectory = storage_path('framework/testing/optimize-tag-images-'.uniqid());
        File::ensureDirectoryExists($this->libraryDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->libraryDirectory);

        parent::tearDown();
    }

    #[Test]
    public function it_converts_oversized_png_to_webp_within_max_bounds(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required.');
        }

        $source = $this->libraryDirectory.'/sample.png';
        $this->writePngFixture($source, 2048, 1152);

        $result = app(OptimizeSeederTagImages::class)($this->libraryDirectory);

        $webp = $this->libraryDirectory.'/sample.webp';

        $this->assertSame(1, $result['converted']);
        $this->assertSame(0, $result['failed']);
        $this->assertFileDoesNotExist($source);
        $this->assertFileExists($webp);

        $size = getimagesize($webp);
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(OptimizeSeederTagImages::DEFAULT_MAX_WIDTH, $size[0]);
        $this->assertLessThanOrEqual(OptimizeSeederTagImages::DEFAULT_MAX_HEIGHT, $size[1]);
        $this->assertSame(1536, $size[0]);
        $this->assertSame(864, $size[1]);
        $this->assertGreaterThan(0, $result['bytes_after']);
        $this->assertGreaterThan($result['bytes_after'], $result['bytes_before']);
        $this->assertSame(File::size($webp), $result['bytes_after']);
    }

    #[Test]
    public function dry_run_does_not_modify_files(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required.');
        }

        $source = $this->libraryDirectory.'/keep-me.png';
        $this->writePngFixture($source, 64, 36);

        $result = app(OptimizeSeederTagImages::class)($this->libraryDirectory, dryRun: true);

        $this->assertSame(1, $result['converted']);
        $this->assertFileExists($source);
        $this->assertFileDoesNotExist($this->libraryDirectory.'/keep-me.webp');
    }

    private function writePngFixture(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);
        $fill = imagecolorallocate($image, 40, 80, 120);
        $this->assertNotFalse($fill);
        imagefilledrectangle($image, 0, 0, $width, $height, $fill);
        imagepng($image, $path);
        imagedestroy($image);
    }
}
