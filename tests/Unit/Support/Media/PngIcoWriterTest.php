<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Media;

use App\Support\Media\PngIcoWriter;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class PngIcoWriterTest extends TestCase
{
    #[Test]
    public function it_encodes_a_png_compressed_ico(): void
    {
        $png = $this->solidPng(16, 16);
        $ico = PngIcoWriter::encode([$png, $this->solidPng(32, 32)]);

        $this->assertSame(0, unpack('v', substr($ico, 0, 2))[1]);
        $this->assertSame(1, unpack('v', substr($ico, 2, 2))[1]);
        $this->assertSame(2, unpack('v', substr($ico, 4, 2))[1]);
        $this->assertSame(16, ord($ico[6]));
        $this->assertSame(16, ord($ico[7]));
        $this->assertSame(32, ord($ico[22]));
        $this->assertSame(32, ord($ico[23]));
        $this->assertStringContainsString("\x89PNG\r\n\x1a\n", $ico);
    }

    #[Test]
    public function it_rejects_an_empty_png_list(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('At least one PNG is required to build an ICO.');

        PngIcoWriter::encode([]);
    }

    private function solidPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($image);

        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $this->assertNotFalse($transparent);
        imagefill($image, 0, 0, $transparent);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($png);

        return $png;
    }
}
