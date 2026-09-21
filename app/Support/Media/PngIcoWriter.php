<?php

declare(strict_types=1);

namespace App\Support\Media;

use RuntimeException;

final class PngIcoWriter
{
    /**
     * Build a PNG-compressed ICO (Vista+).
     *
     * @param  list<string>  $pngBinaries
     */
    public static function encode(array $pngBinaries): string
    {
        if ($pngBinaries === []) {
            throw new RuntimeException('At least one PNG is required to build an ICO.');
        }

        $count = count($pngBinaries);
        $offset = 6 + (16 * $count);
        $directory = '';
        $imageData = '';

        foreach ($pngBinaries as $png) {
            if (strlen($png) < 24 || ! str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
                throw new RuntimeException('ICO entry must be a PNG binary.');
            }

            $width = unpack('N', substr($png, 16, 4))[1];
            $height = unpack('N', substr($png, 20, 4))[1];
            $wByte = $width >= 256 ? 0 : $width;
            $hByte = $height >= 256 ? 0 : $height;
            $size = strlen($png);

            $directory .= pack('CCCCvvVV', $wByte, $hByte, 0, 0, 1, 32, $size, $offset);
            $imageData .= $png;
            $offset += $size;
        }

        return pack('vvv', 0, 1, $count).$directory.$imageData;
    }
}
