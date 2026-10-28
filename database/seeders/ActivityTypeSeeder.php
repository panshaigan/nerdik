<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Services\ActivitySuggestedBringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Production seeder for activity types.
 */
class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ActivityType::slugs() as $slug) {
            $exists = DB::table('activity_types')->where('slug', $slug)->exists();

            if ($exists) {
                continue;
            }

            DB::table('activity_types')->insert([
                'slug' => $slug,
                'max_participants_limit' => ActivityType::defaultMaxParticipantsLimitForSlug($slug),
            ]);
        }

        $this->seedSuggestedBringDefaults();
    }

    private function seedSuggestedBringDefaults(): void
    {
        $bring = app(ActivitySuggestedBringService::class);

        $rpg = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        if ($rpg !== null) {
            $bring->syncBringLabelsForBringable($rpg, ['Paper', 'Pencil']);
        }

        $board = ActivityType::findBySlug(ActivityType::SLUG_BOARD);
        if ($board !== null) {
            $bring->syncBringLabelsForBringable($board, ['The game (box)']);
        }
    }
}
