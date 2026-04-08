<?php

use App\Models\Activity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            if (! Schema::hasColumn('activities', 'hosting_mode')) {
                $table->string('hosting_mode', 32)->default(Activity::HOSTING_MODE_DRAFT)->after('activity_type_id');
                $table->index('hosting_mode');
            }
            if (! Schema::hasColumn('activities', 'place_id')) {
                $table->foreignId('place_id')->nullable()->after('hosting_mode')->constrained('places')->nullOnDelete();
            }
            if (! Schema::hasColumn('activities', 'starts_at')) {
                $table->dateTime('starts_at')->nullable()->after('place_id');
            }
            if (! Schema::hasColumn('activities', 'ends_at')) {
                $table->dateTime('ends_at')->nullable()->after('starts_at');
            }
        });

        DB::statement("
            UPDATE activities a
            LEFT JOIN slots s ON s.activity_id = a.id
            LEFT JOIN events e ON e.id = s.event_id
            SET a.hosting_mode = CASE
                WHEN s.id IS NOT NULL AND s.event_id IS NOT NULL THEN '".Activity::HOSTING_MODE_SCHEDULED_ON_EVENT."'
                WHEN EXISTS (
                    SELECT 1
                    FROM activity_proposals ap
                    WHERE ap.activity_id = a.id
                      AND ap.status = 'pending'
                ) THEN '".Activity::HOSTING_MODE_PROPOSED_TO_EVENT."'
                ELSE '".Activity::HOSTING_MODE_DRAFT."'
            END
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table): void {
            if (Schema::hasColumn('activities', 'ends_at')) {
                $table->dropColumn('ends_at');
            }
            if (Schema::hasColumn('activities', 'starts_at')) {
                $table->dropColumn('starts_at');
            }
            if (Schema::hasColumn('activities', 'place_id')) {
                $table->dropForeign(['place_id']);
                $table->dropColumn('place_id');
            }
            if (Schema::hasColumn('activities', 'hosting_mode')) {
                $table->dropIndex(['hosting_mode']);
                $table->dropColumn('hosting_mode');
            }
        });
    }
};
