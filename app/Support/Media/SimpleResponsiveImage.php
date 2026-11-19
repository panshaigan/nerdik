<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImage;

final class SimpleResponsiveImage extends ResponsiveImage
{
    public function generatedFor(): string
    {
        if ($this->usesSimpleFileName()) {
            return 'media_library_original';
        }

        return parent::generatedFor();
    }

    public function width(): int
    {
        $parsed = $this->simpleWidthFromFileName();

        if ($parsed !== null) {
            return $parsed;
        }

        return parent::width();
    }

    public function height(): int
    {
        if ($this->usesSimpleFileName()) {
            return 1;
        }

        return parent::height();
    }

    private function usesSimpleFileName(): bool
    {
        return $this->simpleWidthFromFileName() !== null;
    }

    private function simpleWidthFromFileName(): ?int
    {
        if (preg_match('/^'.preg_quote(SimpleMediaFileNamer::RESPONSIVE_BASENAME, '/').'_(\d+)\./', $this->fileName, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
