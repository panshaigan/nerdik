<?php

use App\Models\ActivityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_participants_limit')
                ->default(ActivityType::DEFAULT_MAX_PARTICIPANTS_LIMIT)
                ->after('slug');
        });

        foreach (ActivityType::slugs() as $slug) {
            DB::table('activity_types')
                ->where('slug', $slug)
                ->update([
                    'max_participants_limit' => ActivityType::defaultMaxParticipantsLimitForSlug($slug),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropColumn('max_participants_limit');
        });
    }
};
