<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class TagPopularityRecalculator
{
    public function recalculateAll(): int
    {
        Tag::query()->update(['popularity_score' => 0]);

        $countsByTagId = $this->countsByTagId();

        foreach ($countsByTagId as $tagId => $score) {
            Tag::query()->whereKey($tagId)->update(['popularity_score' => $score]);
        }

        return count($countsByTagId);
    }

    /**
     * @param  list<int>  $tagIds
     */
    public function recalculateForTagIds(array $tagIds): void
    {
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
        if ($tagIds === []) {
            return;
        }

        $countsByTagId = $this->countsByTagId($tagIds);

        foreach ($tagIds as $tagId) {
            Tag::query()->whereKey($tagId)->update([
                'popularity_score' => $countsByTagId[$tagId] ?? 0,
            ]);
        }
    }

    /**
     * @param  list<int>|null  $tagIds
     * @return array<int, int>
     */
    private function countsByTagId(?array $tagIds = null): array
    {
        $activityMorph = (new Activity)->getMorphClass();

        $query = DB::table('taggables')
            ->select('tag_id', DB::raw('COUNT(*) as score'))
            ->where('taggable_type', $activityMorph)
            ->whereIn('taggable_id', Activity::query()->attachedToPublicEvent(true)->select('activities.id'))
            ->groupBy('tag_id');

        if ($tagIds !== null) {
            $query->whereIn('tag_id', $tagIds);
        }

        /** @var array<int, int> $out */
        $out = $query
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->tag_id => (int) $row->score])
            ->all();

        return $out;
    }
}
