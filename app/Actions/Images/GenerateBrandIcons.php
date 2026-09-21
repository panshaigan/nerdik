<?php

declare(strict_types=1);

namespace App\Actions\Images;

use App\Support\Media\PngIcoWriter;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class GenerateBrandIcons
{
    /**
     * Rasterize square browser icons from a (optionally trimmed) brand logo source.
     *
     * @return array{
     *     favicon_ico: string,
     *     favicon_svg: string,
     *     apple_touch_icon: string,
     *     sizes: list<int>
     * }
     */
    public function __invoke(string $workingSourcePath): array
    {
        $config = config('media.brand_logo.icons');

        if (! is_array($config) || ! ($config['enabled'] ?? true)) {
            return [
                'favicon_ico' => '',
                'favicon_svg' => '',
                'apple_touch_icon' => '',
                'sizes' => [],
            ];
        }

        if (! File::isFile($workingSourcePath)) {
            throw new RuntimeException("Brand icon source not found at [{$workingSourcePath}].");
        }

        $faviconIcoRelative = (string) ($config['favicon_ico'] ?? 'favicon.ico');
        $faviconSvgRelative = (string) ($config['favicon_svg'] ?? 'favicon.svg');
        $appleTouchRelative = (string) ($config['apple_touch_icon'] ?? 'apple-touch-icon.png');
        $appleTouchSize = (int) ($config['apple_touch_size'] ?? 180);
        $svgSize = (int) ($config['favicon_svg_size'] ?? 512);
        $paddingRatio = (float) ($config['padding_ratio'] ?? 0.08);

        /** @var list<int> $icoSizes */
        $icoSizes = array_values(array_map('intval', $config['favicon_ico_sizes'] ?? [16, 32]));

        if ($icoSizes === []) {
            throw new RuntimeException('media.brand_logo.icons.favicon_ico_sizes must not be empty.');
        }

        $pngBySize = [];

        foreach (array_unique([...$icoSizes, $appleTouchSize, $svgSize]) as $size) {
            $pngBySize[$size] = $this->renderSquarePng($workingSourcePath, $size, $paddingRatio);
        }

        $icoPngs = [];

        foreach ($icoSizes as $size) {
            $icoPngs[] = $pngBySize[$size];
        }

        $faviconIcoPath = public_path($faviconIcoRelative);
        $faviconSvgPath = public_path($faviconSvgRelative);
        $appleTouchPath = public_path($appleTouchRelative);

        File::ensureDirectoryExists(dirname($faviconIcoPath));
        File::ensureDirectoryExists(dirname($faviconSvgPath));
        File::ensureDirectoryExists(dirname($appleTouchPath));

        File::put($faviconIcoPath, PngIcoWriter::encode($icoPngs));
        File::put($appleTouchPath, $pngBySize[$appleTouchSize]);
        File::put($faviconSvgPath, $this->svgWrappingPng($pngBySize[$svgSize], $svgSize));

        return [
            'favicon_ico' => $faviconIcoRelative,
            'favicon_svg' => $faviconSvgRelative,
            'apple_touch_icon' => $appleTouchRelative,
            'sizes' => array_values(array_unique([...$icoSizes, $appleTouchSize, $svgSize])),
        ];
    }

    private function renderSquarePng(string $sourcePath, int $size, float $paddingRatio): string
    {
        if ($size < 1) {
            throw new RuntimeException("Invalid icon size [{$size}].");
        }

        $source = @imagecreatefromwebp($sourcePath);

        if ($source === false) {
            $source = @imagecreatefromstring((string) file_get_contents($sourcePath));
        }

        if ($source === false) {
            throw new RuntimeException("Could not read brand icon source at [{$sourcePath}].");
        }

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $inner = max(1, (int) round($size * (1 - (2 * $paddingRatio))));
            $scale = min($inner / $sourceWidth, $inner / $sourceHeight);
            $drawWidth = max(1, (int) round($sourceWidth * $scale));
            $drawHeight = max(1, (int) round($sourceHeight * $scale));
            $offsetX = (int) round(($size - $drawWidth) / 2);
            $offsetY = (int) round(($size - $drawHeight) / 2);

            $canvas = imagecreatetruecolor($size, $size);

            if ($canvas === false) {
                throw new RuntimeException('Could not allocate icon canvas.');
            }

            imagesavealpha($canvas, true);
            imagealphablending($canvas, false);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);

            if ($transparent === false) {
                throw new RuntimeException('Could not allocate transparent icon background.');
            }

            imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
            imagealphablending($canvas, true);
            imagecopyresampled(
                $canvas,
                $source,
                $offsetX,
                $offsetY,
                0,
                0,
                $drawWidth,
                $drawHeight,
                $sourceWidth,
                $sourceHeight,
            );

            ob_start();
            imagepng($canvas, null, 6);
            $png = ob_get_clean();
            imagedestroy($canvas);

            if (! is_string($png) || $png === '') {
                throw new RuntimeException('Failed to encode icon PNG.');
            }

            return $png;
        } finally {
            imagedestroy($source);
        }
    }

    private function svgWrappingPng(string $pngBinary, int $size): string
    {
        $href = 'data:image/png;base64,'.base64_encode($pngBinary);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$size} {$size}" role="img" aria-label="nerdik">
  <image width="{$size}" height="{$size}" href="{$href}"/>
</svg>

SVG;
    }
}
