<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

trait EnsuresBrandLogoManifest
{
    protected function ensureBrandLogoManifest(): void
    {
        $outputDir = public_path('images/app/brand');
        $manifestPath = $outputDir.'/manifest.json';

        if ($this->brandLogoAssetsAreReady($outputDir)) {
            return;
        }

        $lockPath = storage_path('framework/cache/brand-logo-fixture.lock');
        File::ensureDirectoryExists(dirname($lockPath));

        $lock = fopen($lockPath, 'c+');

        if ($lock === false) {
            throw new RuntimeException('Could not open brand logo fixture lock.');
        }

        try {
            flock($lock, LOCK_EX);

            if ($this->brandLogoAssetsAreReady($outputDir)) {
                return;
            }

            $fixtureDir = base_path('tests/fixtures/brand-logo');

            if (! File::isDirectory($fixtureDir)) {
                throw new RuntimeException('Brand logo test fixtures are missing at [tests/fixtures/brand-logo].');
            }

            File::ensureDirectoryExists($outputDir);

            foreach (File::files($fixtureDir) as $file) {
                File::copy($file->getPathname(), $outputDir.'/'.$file->getFilename());
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        if (! $this->brandLogoAssetsAreReady($outputDir)) {
            throw new RuntimeException('Brand logo test fixtures could not be synced to [public/images/app/brand].');
        }
    }

    private function brandLogoAssetsAreReady(string $outputDir): bool
    {
        return File::isFile($outputDir.'/manifest.json')
            && File::isFile($outputDir.'/40w.webp');
    }
}
