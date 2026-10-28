<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SyncGameTagSuggestedBringCatalog
{
    private const BRINGABLE_TYPE_TAG = 'tag';

    /**
     * @return array{imported: int, skipped: int}
     */
    public function importFromJsonFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Suggested bring catalog not readable: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException("Suggested bring catalog is not valid JSON: {$path}");
        }

        $entries = $decoded['entries'] ?? null;
        if (! is_array($entries)) {
            throw new RuntimeException("Suggested bring catalog missing entries: {$path}");
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($entries, &$imported, &$skipped): void {
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    $skipped++;

                    continue;
                }

                $tagBlock = $entry['tag'] ?? null;
                if (! is_array($tagBlock)) {
                    $skipped++;

                    continue;
                }

                $tagId = $this->resolveTagId($tagBlock);
                if ($tagId === null) {
                    $skipped++;

                    continue;
                }

                $lines = $entry['suggested_bring'] ?? [];
                if (! is_array($lines)) {
                    $lines = [];
                }

                $this->replaceTagSuggestedBring($tagId, $lines);
                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param  array{id?: int|string|null, label?: string|null}  $tagBlock
     */
    public function resolveTagId(array $tagBlock): ?int
    {
        $candidateId = (int) ($tagBlock['id'] ?? 0);
        if ($candidateId > 0 && DB::table('tags')->where('id', $candidateId)->exists()) {
            return $candidateId;
        }

        $label = trim((string) ($tagBlock['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $byLabel = DB::table('tag_translations')
            ->where('locale', 'en')
            ->whereRaw('LOWER(label) = LOWER(?)', [$label])
            ->value('tag_id');

        return $byLabel !== null ? (int) $byLabel : null;
    }

    /**
     * @param  list<mixed>  $lines
     */
    public function replaceTagSuggestedBring(int $tagId, array $lines): void
    {
        $itemIds = DB::table('suggested_bring_items')
            ->where('bringable_type', self::BRINGABLE_TYPE_TAG)
            ->where('bringable_id', $tagId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($itemIds !== []) {
            DB::table('activity_suggested_bring_provisions')
                ->whereIn('suggested_bring_item_id', $itemIds)
                ->delete();
            DB::table('suggested_bring_item_translations')
                ->whereIn('suggested_bring_item_id', $itemIds)
                ->delete();
            DB::table('suggested_bring_items')
                ->whereIn('id', $itemIds)
                ->delete();
        }

        $now = now();
        $sort = 0;

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $en = mb_substr(trim((string) ($line['en'] ?? '')), 0, 255);
            $pl = mb_substr(trim((string) ($line['pl'] ?? '')), 0, 255);

            if ($en === '' && $pl === '') {
                continue;
            }

            $itemId = DB::table('suggested_bring_items')->insertGetId([
                'bringable_type' => self::BRINGABLE_TYPE_TAG,
                'bringable_id' => $tagId,
                'sort_order' => $sort,
                'host_provides' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($en !== '') {
                DB::table('suggested_bring_item_translations')->insert([
                    'suggested_bring_item_id' => $itemId,
                    'locale' => 'en',
                    'label' => $en,
                ]);
            }

            if ($pl !== '') {
                DB::table('suggested_bring_item_translations')->insert([
                    'suggested_bring_item_id' => $itemId,
                    'locale' => 'pl',
                    'label' => $pl,
                ]);
            }

            $sort++;
        }
    }
}
