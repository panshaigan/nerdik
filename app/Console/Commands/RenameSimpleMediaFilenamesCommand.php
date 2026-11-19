<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\RenameSimpleMediaFilenames;
use App\Models\Media;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:rename-simple-filenames
        {--dry-run : Report changes without renaming files}
        {--force : Run without confirmation in production}
        {--skip-responsives : Clear legacy names but do not regenerate responsives}
        {--collection=* : Limit to collection names (images, logo, gallery, event_listing)}')]
#[Description('Rename media originals to original.webp and responsives to responsive_{width}.webp')]
final class RenameSimpleMediaFilenamesCommand extends Command
{
    /**
     * @var list<string>
     */
    private const DEFAULT_COLLECTIONS = ['images', 'logo', 'gallery', 'event_listing'];

    public function handle(RenameSimpleMediaFilenames $rename): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $regenerate = ! (bool) $this->option('skip-responsives');

        if (! $this->option('force') && ! $dryRun && ! $this->confirm('Rename media files to simple filenames on this environment?', true)) {
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

        $renamed = 0;
        $unchanged = 0;
        $failed = 0;

        Media::query()
            ->whereIn('collection_name', $collections)
            ->orderBy('id')
            ->each(function (Media $media) use ($rename, $dryRun, $regenerate, &$renamed, &$unchanged, &$failed): void {
                $result = $rename($media, dryRun: $dryRun, regenerateResponsives: $regenerate);

                if ($result['status'] === 'renamed') {
                    $renamed++;
                    $this->line(sprintf(
                        'Media [%d]: %s simple filenames',
                        $media->id,
                        $dryRun ? 'would rename to' : 'renamed to',
                    ));

                    return;
                }

                if ($result['status'] === 'failed') {
                    $failed++;
                    $this->warn(sprintf(
                        'Media [%d]: failed (%s)',
                        $media->id,
                        $result['reason'] ?? 'unknown',
                    ));

                    return;
                }

                $unchanged++;
            });

        $this->info(sprintf(
            'Simple filename rename complete. renamed=%d unchanged=%d failed=%d%s',
            $renamed,
            $unchanged,
            $failed,
            $dryRun ? ' (dry-run)' : '',
        ));

        if (! $dryRun && $renamed > 0) {
            $this->newLine();
            $this->line('Next: prune obsolete conversion twins if any remain:');
            $this->line('  php artisan media:prune-obsolete-derivatives --force');
        }

        return self::SUCCESS;
    }
}
