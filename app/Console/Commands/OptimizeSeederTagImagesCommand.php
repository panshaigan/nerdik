<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Seeders\OptimizeSeederTagImages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seeders:optimize-tag-images
    {--dry-run : Report changes without writing files}
    {--directory= : Override the tag_images directory (defaults to database/seeders/tag_images)}')]
#[Description('Convert seeder tag_images PNG/JPG masters to WebP at Fit::Max 1536 (matching media conversions)')]
final class OptimizeSeederTagImagesCommand extends Command
{
    public function handle(OptimizeSeederTagImages $optimize): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $directory = (string) ($this->option('directory') ?: database_path('seeders/tag_images'));

        $result = $optimize($directory, $dryRun);

        foreach ($result['files'] as $file) {
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file['path']);

            if ($file['status'] === 'converted') {
                $sizeNote = $dryRun && $file['bytes_after'] === 0
                    ? sprintf('%d bytes', $file['bytes_before'])
                    : sprintf('%d -> %d bytes', $file['bytes_before'], $file['bytes_after']);

                $this->line(sprintf(
                    '%s %s (%s)',
                    $dryRun ? 'Would convert' : 'Converted',
                    $relative,
                    $sizeNote,
                ));

                continue;
            }

            if ($file['status'] === 'skipped') {
                continue;
            }

            $this->warn(sprintf(
                'Failed %s (%s)',
                $relative,
                $file['reason'] ?? 'unknown',
            ));
        }

        $this->info(sprintf(
            'Seeder tag image optimize complete. converted=%d skipped=%d failed=%d bytes=%d->%d%s',
            $result['converted'],
            $result['skipped'],
            $result['failed'],
            $result['bytes_before'],
            $result['bytes_after'],
            $dryRun ? ' (dry-run)' : '',
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
