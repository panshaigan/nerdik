<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Copies an image into public/ for tests that exercise public_path-based seeders,
 * and deletes it when the application is torn down.
 */
trait CreatesPublicImageFixture
{
    protected function createPublicImageFixture(string $relativePath, ?string $sourceAbsolutePath = null): string
    {
        $sourceAbsolutePath ??= base_path('tests/fixtures/tag-sample.jpg');
        $absolutePath = public_path($relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        copy($sourceAbsolutePath, $absolutePath);

        $this->beforeApplicationDestroyed(function () use ($absolutePath): void {
            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
        });

        return $relativePath;
    }
}
