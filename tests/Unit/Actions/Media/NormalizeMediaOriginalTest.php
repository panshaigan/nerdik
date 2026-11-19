<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Media;

use App\Actions\Media\NormalizeMediaOriginal;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NormalizeMediaOriginalTest extends TestCase
{
    #[Test]
    public function it_converts_jpeg_to_webp_temp_file(): void
    {
        $fixture = base_path('tests/fixtures/tag-sample.jpg');

        $result = app(NormalizeMediaOriginal::class)($fixture);

        $this->assertTrue($result['changed']);
        $this->assertTrue($result['temporary']);
        $this->assertTrue(File::isFile($result['path']));
        $this->assertStringEndsWith('.webp', $result['path']);
        $this->assertNotNull($result['width']);
        $this->assertLessThanOrEqual((int) config('media.max_edge', 1536), (int) $result['width']);

        File::delete($result['path']);
    }

    #[Test]
    public function it_keeps_existing_webp_within_max_edge(): void
    {
        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        $tempWebp = sys_get_temp_dir().'/normalize-keep-'.uniqid('', true).'.webp';

        $normalized = app(NormalizeMediaOriginal::class)($fixture);
        File::move($normalized['path'], $tempWebp);

        $result = app(NormalizeMediaOriginal::class)($tempWebp);

        $this->assertFalse($result['changed']);
        $this->assertFalse($result['temporary']);
        $this->assertSame($tempWebp, $result['path']);

        File::delete($tempWebp);
    }
}
