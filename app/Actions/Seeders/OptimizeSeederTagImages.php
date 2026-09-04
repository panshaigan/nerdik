<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use SplFileInfo;
use Throwable;

final class OptimizeSeederTagImages
{
    public const DEFAULT_MAX_WIDTH = 1536;

    public const DEFAULT_MAX_HEIGHT = 1536;

    /** @var list<string> */
    private const SOURCE_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    /**
     * @return array{
     *     converted: int,
     *     skipped: int,
     *     failed: int,
     *     bytes_before: int,
     *     bytes_after: int,
     *     files: list<array{
     *         status: 'converted'|'skipped'|'failed',
     *         path: string,
     *         reason: ?string,
     *         bytes_before: int,
     *         bytes_after: int,
     *     }>
     * }
     */
    public function __invoke(
        string $directory,
        bool $dryRun = false,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $maxHeight = self::DEFAULT_MAX_HEIGHT,
    ): array {
        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;
        $files = [];

        if (! File::isDirectory($directory)) {
            return [
                'converted' => 0,
                'skipped' => 0,
                'failed' => 0,
                'bytes_before' => 0,
                'bytes_after' => 0,
                'files' => [],
            ];
        }

        foreach (File::allFiles($directory) as $file) {
            if (! $this->isSourceImage($file)) {
                continue;
            }

            $result = $this->convertFile($file->getPathname(), $dryRun, $maxWidth, $maxHeight);
            $files[] = $result;
            $bytesBefore += $result['bytes_before'];
            $bytesAfter += $result['bytes_after'];

            match ($result['status']) {
                'converted' => $converted++,
                'skipped' => $skipped++,
                'failed' => $failed++,
            };
        }

        return [
            'converted' => $converted,
            'skipped' => $skipped,
            'failed' => $failed,
            'bytes_before' => $bytesBefore,
            'bytes_after' => $bytesAfter,
            'files' => $files,
        ];
    }

    /**
     * @return array{
     *     status: 'converted'|'skipped'|'failed',
     *     path: string,
     *     reason: ?string,
     *     bytes_before: int,
     *     bytes_after: int,
     * }
     */
    private function convertFile(string $sourcePath, bool $dryRun, int $maxWidth, int $maxHeight): array
    {
        $bytesBefore = File::size($sourcePath);
        $targetPath = $this->webpPathFor($sourcePath);

        if (! is_readable($sourcePath)) {
            return $this->fileResult('failed', $sourcePath, 'unreadable', $bytesBefore, 0);
        }

        if ($dryRun) {
            return $this->fileResult('converted', $sourcePath, null, $bytesBefore, 0);
        }

        $tempPath = dirname($targetPath).DIRECTORY_SEPARATOR.'.'.basename($targetPath).'.'.uniqid('', true).'.tmp';

        try {
            Image::load($sourcePath)
                ->fit(Fit::Max, $maxWidth, $maxHeight)
                ->format('webp')
                ->quality((int) config('media.conversion_qualities.webp', 85))
                ->save($tempPath);

            if (! File::isFile($tempPath)) {
                throw new RuntimeException('WebP output was not created.');
            }

            File::move($tempPath, $targetPath);
            $bytesAfter = File::size($targetPath);

            if ($sourcePath !== $targetPath && File::isFile($sourcePath)) {
                File::delete($sourcePath);
            }

            return $this->fileResult('converted', $sourcePath, null, $bytesBefore, $bytesAfter);
        } catch (Throwable $exception) {
            if (File::isFile($tempPath)) {
                File::delete($tempPath);
            }

            return $this->fileResult('failed', $sourcePath, $exception->getMessage(), $bytesBefore, 0);
        }
    }

    private function isSourceImage(SplFileInfo $file): bool
    {
        return in_array(strtolower($file->getExtension()), self::SOURCE_EXTENSIONS, true);
    }

    private function webpPathFor(string $sourcePath): string
    {
        return dirname($sourcePath).DIRECTORY_SEPARATOR.pathinfo($sourcePath, PATHINFO_FILENAME).'.webp';
    }

    /**
     * @return array{
     *     status: 'converted'|'skipped'|'failed',
     *     path: string,
     *     reason: ?string,
     *     bytes_before: int,
     *     bytes_after: int,
     * }
     */
    private function fileResult(
        string $status,
        string $path,
        ?string $reason,
        int $bytesBefore,
        int $bytesAfter,
    ): array {
        return [
            'status' => $status,
            'path' => $path,
            'reason' => $reason,
            'bytes_before' => $bytesBefore,
            'bytes_after' => $bytesAfter,
        ];
    }
}
