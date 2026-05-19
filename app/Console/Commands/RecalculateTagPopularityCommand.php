<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Tag;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tags:recalculate-popularity')]
#[Description('Recalculate tag popularity scores from upcoming browse-visible activity usage')]
class RecalculateTagPopularityCommand extends Command
{
    public function handle(): int
    {
        Tag::query()->update(['popularity_score' => 0]);

        $activityMorph = (new Activity)->getMorphClass();

        $counts = DB::table('taggables')
            ->select('tag_id', DB::raw('COUNT(*) as score'))
            ->where('taggable_type', $activityMorph)
            ->whereIn('taggable_id', Activity::query()->attachedToPublicEvent(true)->select('activities.id'))
            ->groupBy('tag_id')
            ->get();

        foreach ($counts as $row) {
            Tag::query()->whereKey((int) $row->tag_id)->update(['popularity_score' => (int) $row->score]);
        }

        $this->info(sprintf('Updated popularity for %d tag(s).', $counts->count()));

        return self::SUCCESS;
    }
}
