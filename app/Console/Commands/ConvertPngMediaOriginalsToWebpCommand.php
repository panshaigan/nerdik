<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\ConvertMediaOriginalToWebp;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('media:convert-png-originals-to-webp
    {--dry-run : Report changes without writing files or updating rows}
    {--limit= : Max media rows to convert this run}
    {--id=* : Only convert these media IDs}')]
#[Description('Replace oversized Spatie originals with conversion WebP (or re-encode PNG masters)')]
final class ConvertPngMediaOriginalsToWebpCommand extends Command
{
    public function handle(ConvertMediaOriginalToWebp $convert): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $bytesBefore = 0;
        $bytesAfter = 0;
        $orphansRemoved = 0;

        foreach ($this->candidateMedia() as $media) {
            $originalFileName = (string) $media->file_name;
            $result = $convert($media, $dryRun);

            $bytesBefore += $result['bytes_before'];
            $bytesAfter += $result['bytes_after'];
            $orphansRemoved += $result['orphans_removed'];

            if ($result['status'] === 'converted') {
                $converted++;
                $sizeNote = $dryRun && $result['bytes_after'] === 0
                    ? sprintf('%d bytes', $result['bytes_before'])
                    : sprintf('%d -> %d bytes', $result['bytes_before'], $result['bytes_after']);

                $this->line(sprintf(
                    '%s media [%d] %s (%s, via %s)%s',
                    $dryRun ? 'Would convert' : 'Converted',
                    $media->id,
                    $originalFileName,
                    $sizeNote,
                    $result['source'] ?? 'unknown',
                    $result['orphans_removed'] > 0
                        ? sprintf(', orphans=%d', $result['orphans_removed'])
                        : '',
                ));

                continue;
            }

            if ($result['status'] === 'skipped') {
                $skipped++;

                continue;
            }

            $failed++;
            $this->warn(sprintf(
                'Failed media [%d] %s (%s)',
                $media->id,
                $originalFileName,
                $result['reason'] ?? 'unknown',
            ));
        }

        $this->info(sprintf(
            'Original->WebP shrink complete. converted=%d skipped=%d failed=%d orphans_removed=%d bytes=%d->%d%s',
            $converted,
            $skipped,
            $failed,
            $orphansRemoved,
            $bytesBefore,
            $bytesAfter,
            $dryRun ? ' (dry-run)' : '',
        ));

        $this->newLine();
        $this->line('Prod (compose-exec):');
        $this->line('  ./scripts/compose-exec.sh prod exec app php artisan media:convert-png-originals-to-webp --dry-run');
        $this->line('  ./scripts/compose-exec.sh prod exec app php artisan media:convert-png-originals-to-webp');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, Media>|LazyCollection<int, Media>
     */
    private function candidateMedia(): Collection|LazyCollection
    {
        $query = $this->candidateQuery()->orderBy('id');

        $limit = $this->option('limit');

        if ($limit !== null && $limit !== '') {
            return $query->limit(max(1, (int) $limit))->get();
        }

        return $query->lazyById();
    }

    /**
     * @return Builder<Media>
     */
    private function candidateQuery(): Builder
    {
        $query = Media::query()->where(function (Builder $builder): void {
            $builder
                ->where('mime_type', 'image/png')
                ->orWhere('file_name', 'like', '%.png')
                ->orWhere('generated_conversions->webp', true);
        });

        /** @var list<string|int> $ids */
        $ids = $this->option('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        return $query;
    }
}
