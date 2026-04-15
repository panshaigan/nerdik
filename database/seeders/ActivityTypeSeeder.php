<?php

namespace Database\Seeders;

use App\Models\ActivityType;
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
            DB::table('activity_types')->updateOrInsert(['slug' => $slug]);
        }
    }
}
