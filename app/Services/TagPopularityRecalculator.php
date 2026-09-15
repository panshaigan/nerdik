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
        $this->applyScores($countsByTagId);

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
        $scores = [];

        foreach ($tagIds as $tagId) {
            $scores[$tagId] = $countsByTagId[$tagId] ?? 0;
        }

        $this->applyScores($scores);
    }

    /**
     * @param  array<int, int>  $scoresByTagId
     */
    private function applyScores(array $scoresByTagId): void
    {
        if ($scoresByTagId === []) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        $ids = array_keys($scoresByTagId);
        $cases = [];
        $bindings = [];

        foreach ($scoresByTagId as $tagId => $score) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $tagId;
            $bindings[] = $score;
        }

        $caseSql = 'CASE id '.implode(' ', $cases).' END';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $bindings = array_merge($bindings, $ids);

        if ($driver === 'pgsql') {
            DB::update(
                "UPDATE tags SET popularity_score = ({$caseSql})::integer WHERE id IN ({$placeholders})",
                $bindings
            );

            return;
        }

        DB::update(
            "UPDATE tags SET popularity_score = {$caseSql} WHERE id IN ({$placeholders})",
            $bindings
        );
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
