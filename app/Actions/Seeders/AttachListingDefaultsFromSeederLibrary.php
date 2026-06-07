<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\ActivityType;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;

final class AttachListingDefaultsFromSeederLibrary
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif'];

    public function __construct(
        private readonly AttachModelMediaFromPublic $attachMedia,
    ) {}

    public function __invoke(string $defaultDirectory): void
    {
        $this->seedActivityDefaults($defaultDirectory.'/Activity');
        $this->seedEventDefaults($defaultDirectory.'/Event');
    }

    private function seedActivityDefaults(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            return;
        }

        foreach (File::files($directory) as $file) {
            if (! $this->isImageFile($file)) {
                continue;
            }

            $this->attachActivityImages(
                $this->resolveActivityTypeSlug($file->getFilename()),
                [$file->getPathname()],
            );
        }

        foreach (File::directories($directory) as $subdirectory) {
            $paths = $this->imagePathsInDirectory($subdirectory);

            if ($paths === []) {
                continue;
            }

            $this->attachActivityImages(basename($subdirectory), $paths);
        }
    }

    private function seedEventDefaults(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            return;
        }

        $paths = $this->imagePathsInDirectory($directory);

        if ($paths === []) {
            return;
        }

        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);

        if ($rpgType === null) {
            return;
        }

        foreach ($paths as $absolutePath) {
            $this->attachMedia->attachFile(
                $rpgType,
                $absolutePath,
                $this->seedSourcePath($absolutePath),
                collection: 'event_listing',
            );
        }
    }

    /**
     * @param  list<string>  $absolutePaths
     */
    private function attachActivityImages(string $slug, array $absolutePaths): void
    {
        $activityType = ActivityType::findBySlug($slug);

        if ($activityType === null) {
            return;
        }

        foreach ($absolutePaths as $absolutePath) {
            $this->attachMedia->attachFile(
                $activityType,
                $absolutePath,
                $this->seedSourcePath($absolutePath),
            );
        }
    }

    private function resolveActivityTypeSlug(string $filename): string
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $normalizedStem = strtolower(str_replace(['_', '-'], ' ', $stem));

        foreach ($this->activityTypeSlugsByLength() as $slug) {
            if (
                str_starts_with($stem, $slug.'_')
                || str_starts_with($normalizedStem, $slug.' ')
                || $stem === $slug
            ) {
                return $slug;
            }
        }

        return ActivityType::SLUG_RPG;
    }

    /**
     * @return list<string>
     */
    private function activityTypeSlugsByLength(): array
    {
        $slugs = ActivityType::slugs();

        usort($slugs, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $slugs;
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
