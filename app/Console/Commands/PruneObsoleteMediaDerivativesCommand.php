<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\PruneObsoleteResponsiveWidths;
use App\Models\Media;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:prune-obsolete-derivatives
        {--dry-run : Report removals without deleting files}
        {--force : Run without confirmation in production}
        {--skip-conversions : Skip deprecated conversion cleanup}
        {--skip-widths : Skip obsolete responsive width pruning}')]
#[Description('Remove deprecated conversion files (e.g. dropped formats) and responsive widths no longer in config')]
final class PruneObsoleteMediaDerivativesCommand extends Command
{
    public function handle(PruneObsoleteResponsiveWidths $pruneWidths): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipConversions = (bool) $this->option('skip-conversions');
        $skipWidths = (bool) $this->option('skip-widths');

        if (! $this->option('force') && ! $dryRun && ! $this->confirm('Prune obsolete media derivatives on this environment?', true)) {
            return self::SUCCESS;
        }

        if (! $skipConversions) {
            $this->info($dryRun
                ? 'Dry-run: would run media-library:clean for deprecated conversions.'
                : 'Cleaning deprecated conversions (media-library:clean)...');

            $this->call('media-library:clean', [
                '--force' => true,
                '--dry-run' => $dryRun,
            ]);
        }

        if (! $skipWidths) {
            $filesRemoved = 0;
            $urlsPruned = 0;
            $mediaTouched = 0;

            Media::query()
                ->orderBy('id')
                ->each(function (Media $media) use ($pruneWidths, $dryRun, &$filesRemoved, &$urlsPruned, &$mediaTouched): void {
                    $result = $pruneWidths($media, $dryRun);
                    $filesRemoved += $result['files_removed'];
                    $urlsPruned += $result['urls_pruned'];

                    if ($result['files_removed'] > 0 || $result['urls_pruned'] > 0) {
                        $mediaTouched++;
                        $this->line(sprintf(
                            'Media [%d]: %sremoved_files=%d urls_pruned=%d',
                            $media->id,
                            $dryRun ? 'would ' : '',
                            $result['files_removed'],
                            $result['urls_pruned'],
                        ));
                    }
                });

            $this->info(sprintf(
                'Responsive width prune complete. media_touched=%d files_%s=%d urls_%s=%d%s',
                $mediaTouched,
                $dryRun ? 'would_remove' : 'removed',
                $filesRemoved,
                $dryRun ? 'would_prune' : 'pruned',
                $urlsPruned,
                $dryRun ? ' (dry-run)' : '',
            ));
        }

        return self::SUCCESS;
    }
}
