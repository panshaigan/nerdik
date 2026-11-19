<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\ReplaceMediaOriginalWithNormalized;
use App\Models\Media;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:normalize-originals
        {--dry-run : Report changes without rewriting files}
        {--force : Run without confirmation in production}
        {--skip-responsives : Do not regenerate original responsives after normalize}
        {--collection=* : Limit to collection names (images, logo, gallery, event_listing)}')]
#[Description('Normalize media originals to WebP ≤ max edge and regenerate original responsives')]
final class NormalizeMediaOriginalsCommand extends Command
{
    /**
     * @var list<string>
     */
    private const DEFAULT_COLLECTIONS = ['images', 'logo', 'gallery', 'event_listing'];

    public function handle(ReplaceMediaOriginalWithNormalized $replace): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipResponsives = (bool) $this->option('skip-responsives');

        if (! $this->option('force') && ! $dryRun && ! $this->confirm('Normalize media originals on this environment?', true)) {
            return self::SUCCESS;
        }

        /** @var list<string> $collections */
        $collections = array_values(array_filter(
            (array) $this->option('collection'),
            fn (mixed $name): bool => is_string($name) && $name !== '',
        ));

        if ($collections === []) {
            $collections = self::DEFAULT_COLLECTIONS;
        }

        $normalized = 0;
        $unchanged = 0;
        $failed = 0;
        $bytesSaved = 0;

        Media::query()
            ->whereIn('collection_name', $collections)
            ->orderBy('id')
            ->each(function (Media $media) use (
                $replace,
                $dryRun,
                $skipResponsives,
                &$normalized,
                &$unchanged,
                &$failed,
                &$bytesSaved,
            ): void {
                $result = $replace(
                    $media,
                    dryRun: $dryRun,
                    regenerateResponsives: ! $dryRun && ! $skipResponsives,
                );

                if ($result['status'] === 'normalized') {
                    $normalized++;
                    $bytesSaved += max(0, $result['bytes_before'] - $result['bytes_after']);
                    $this->line(sprintf(
                        'Media [%d]: %s%d → %d bytes',
                        $media->id,
                        $dryRun ? 'would normalize ' : 'normalized ',
                        $result['bytes_before'],
                        $result['bytes_after'],
                    ));

                    return;
                }

                if ($result['status'] === 'unchanged') {
                    $unchanged++;

                    if (! $dryRun && ! $skipResponsives && ! $media->hasResponsiveImages()) {
                        $replace->regenerateOriginalResponsives($media);
                    }

                    return;
                }

                $failed++;
                $this->warn(sprintf(
                    'Media [%d]: failed (%s)',
                    $media->id,
                    $result['reason'] ?? 'unknown',
                ));
            });

        $this->info(sprintf(
            'Normalize originals complete. normalized=%d unchanged=%d failed=%d bytes_saved≈%d%s',
            $normalized,
            $unchanged,
            $failed,
            $bytesSaved,
            $dryRun ? ' (dry-run)' : '',
        ));

        if (! $dryRun) {
            $this->newLine();
            $this->line('Next: prune obsolete full-size conversions (webp/avif twins):');
            $this->line('  php artisan media:prune-obsolete-derivatives --force');
        }

        return self::SUCCESS;
    }
}
