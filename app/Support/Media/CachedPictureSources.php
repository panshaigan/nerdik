<?php

declare(strict_types=1);

namespace App\Support\Media;

final readonly class CachedPictureSources
{
    public function __construct(
        private string $avifSrcset,
        private string $webpSrcset,
        private string $displaySrc,
        private string $sizes,
        private string $alt,
        private ?int $width,
        private ?int $height,
    ) {}

    public static function fromMediaPictureSources(MediaPictureSources $sources): self
    {
        return new self(
            avifSrcset: $sources->avifSrcset(),
            webpSrcset: $sources->webpSrcset(),
            displaySrc: $sources->displaySrc(),
            sizes: $sources->sizes(),
            alt: $sources->alt(),
            width: $sources->width(),
            height: $sources->height(),
        );
    }

    /**
     * @param  array{
     *     avifSrcset: string,
     *     webpSrcset: string,
     *     displaySrc: string,
     *     sizes: string,
     *     alt: string,
     *     width: int|null,
     *     height: int|null,
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            avifSrcset: (string) $data['avifSrcset'],
            webpSrcset: (string) $data['webpSrcset'],
            displaySrc: (string) $data['displaySrc'],
            sizes: (string) $data['sizes'],
            alt: (string) $data['alt'],
            width: isset($data['width']) ? (int) $data['width'] : null,
            height: isset($data['height']) ? (int) $data['height'] : null,
        );
    }

    /**
     * @return array{
     *     avifSrcset: string,
     *     webpSrcset: string,
     *     displaySrc: string,
     *     sizes: string,
     *     alt: string,
     *     width: int|null,
     *     height: int|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'avifSrcset' => $this->avifSrcset,
            'webpSrcset' => $this->webpSrcset,
            'displaySrc' => $this->displaySrc,
            'sizes' => $this->sizes,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    public function avifSrcset(): string
    {
        return $this->avifSrcset;
    }

    public function webpSrcset(): string
    {
        return $this->webpSrcset;
    }

    public function displaySrc(): string
    {
        return $this->displaySrc;
    }

    public function sizes(): string
    {
        return $this->sizes;
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
}
