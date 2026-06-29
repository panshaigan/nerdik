<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class StaticPictureSources
{
    /**
     * @param  array<string, list<array{width: int, path: string, bytes: int}>>  $variants
     * @param  list<int>  $mobileWidths
     * @param  list<int>  $desktopWidths
     */
    public function __construct(
        private readonly string $sizes,
        private readonly string $alt,
        private readonly array $variants,
        private readonly int $desktopMinWidth,
        private readonly array $mobileWidths,
        private readonly array $desktopWidths,
        private readonly ?int $width = null,
        private readonly ?int $height = null,
    ) {}

    public static function fromAppShellBackground(string $theme): self
    {
        $manifestPath = public_path('images/app/backgrounds/manifest.json');

        if (! File::isFile($manifestPath)) {
            throw new RuntimeException('App shell background manifest not found. Run: php artisan app:generate-shell-backgrounds');
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! is_array($manifest) || ! isset($manifest[$theme]) || ! is_array($manifest[$theme])) {
            throw new RuntimeException("App shell background manifest is missing theme [{$theme}].");
        }

        $themeManifest = $manifest[$theme];
        /** @var array<string, list<array{width: int, path: string, bytes: int}>> $variants */
        $variants = $themeManifest['variants'] ?? [];
        $config = config('media.app_shell_background', []);

        return new self(
            sizes: (string) ($config['sizes'] ?? '100vw'),
            alt: '',
            variants: $variants,
            desktopMinWidth: (int) ($config['desktop_min_width'] ?? 1025),
            mobileWidths: array_map(intval(...), $config['mobile_widths'] ?? [384, 512, 640, 768, 1024]),
            desktopWidths: array_map(intval(...), $config['desktop_widths'] ?? [1536, 1716]),
            width: isset($themeManifest['width']) ? (int) $themeManifest['width'] : null,
            height: isset($themeManifest['height']) ? (int) $themeManifest['height'] : null,
        );
    }

    public function avifMobileSrcset(): string
    {
        return $this->srcsetForFormat('avif', $this->mobileWidths);
    }

    public function avifDesktopSrcset(): string
    {
        return $this->srcsetForFormat('avif', $this->desktopWidths);
    }

    public function webpMobileSrcset(): string
    {
        return $this->srcsetForFormat('webp', $this->mobileWidths);
    }

    public function webpDesktopSrcset(): string
    {
        return $this->srcsetForFormat('webp', $this->desktopWidths);
    }

    public function avifSrcset(): string
    {
        return $this->srcsetForFormat('avif', [...$this->mobileWidths, ...$this->desktopWidths]);
    }

    public function webpSrcset(): string
    {
        return $this->srcsetForFormat('webp', [...$this->mobileWidths, ...$this->desktopWidths]);
    }

    public function jpegSrcset(): string
    {
        return '';
    }

    public function displaySrc(): string
    {
        $largestWebp = $this->largestVariantUrl('webp');

        if ($largestWebp !== null) {
            return $largestWebp;
        }

        $largestAvif = $this->largestVariantUrl('avif');

        if ($largestAvif !== null) {
            return $largestAvif;
        }

        return '';
    }

    public function sizes(): string
    {
        return $this->sizes;
    }

    public function mobileMediaQuery(): string
    {
        return '(max-width: '.($this->desktopMinWidth - 1).'px)';
    }

    public function desktopMediaQuery(): string
    {
        return '(min-width: '.$this->desktopMinWidth.'px)';
    }

    public function alt(): string
    {
        return $this->alt;
    }

    public function width(): ?int
    {
        return $this->width;
    }

    public function height(): ?int
    {
        return $this->height;
    }

    /**
     * @param  list<int>  $allowedWidths
     */
    private function srcsetForFormat(string $format, array $allowedWidths): string
    {
        $entries = $this->variants[$format] ?? [];

        if ($entries === []) {
            return '';
        }

        $allowed = array_flip($allowedWidths);

        $entries = array_values(array_filter(
            $entries,
            fn (array $entry): bool => isset($allowed[$entry['width']]),
        ));

        if ($entries === []) {
            return '';
        }

        usort($entries, fn (array $a, array $b): int => $a['width'] <=> $b['width']);

        return implode(', ', array_map(
            fn (array $entry): string => asset($entry['path']).' '.$entry['width'].'w',
            $entries,
        ));
    }

    private function largestVariantUrl(string $format): ?string
    {
        $entries = $this->variants[$format] ?? [];

        if ($entries === []) {
            return null;
        }

        usort($entries, fn (array $a, array $b): int => $a['width'] <=> $b['width']);

        $largest = $entries[array_key_last($entries)];

        return asset($largest['path']);
    }
}
