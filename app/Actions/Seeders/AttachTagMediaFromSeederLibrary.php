<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Support\Seeders\SeederTagImageMatcher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;

final class AttachTagMediaFromSeederLibrary
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif'];

    public function __construct(
        private readonly SeederTagImageMatcher $matcher,
        private readonly AttachModelMediaFromPublic $attachMedia,
    ) {}

    public function __invoke(string $directory, ?string $categoryKey = null): void
    {
        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            if (! $this->isImageFile($file)) {
                continue;
            }

            $this->attachForDescriptor($file->getFilename(), [$file->getPathname()], $categoryKey);
        }

        foreach (File::directories($directory) as $subdirectory) {
            $paths = $this->imagePathsInDirectory($subdirectory);

            if ($paths === []) {
                continue;
            }

            $this->attachForDescriptor(basename($subdirectory), $paths, $categoryKey);
        }
    }

    /**
     * @param  list<string>  $absolutePaths
     */
    private function attachForDescriptor(string $descriptor, array $absolutePaths, ?string $categoryKey): void
    {
        $parsed = $this->matcher->parseBasename($descriptor);
        $tag = $this->matcher->resolve($parsed['id'], $parsed['slug'], $categoryKey);

        if ($tag === null) {
            return;
        }

        foreach ($absolutePaths as $absolutePath) {
            $seedSource = $this->seedSourcePath($absolutePath);
            $this->attachMedia->attachFile($tag, $absolutePath, $seedSource);
        }
    }

    /**
     * @return list<string>
     */
    private function imagePathsInDirectory(string $directory): array
    {
        $paths = [];

        foreach (File::allFiles($directory) as $file) {
            if ($this->isImageFile($file)) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths);

        return $paths;
    }

    private function isImageFile(SplFileInfo $file): bool
    {
        return in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true);
    }

    private function seedSourcePath(string $absolutePath): string
    {
        $relative = Str::after($absolutePath, base_path().DIRECTORY_SEPARATOR);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}
