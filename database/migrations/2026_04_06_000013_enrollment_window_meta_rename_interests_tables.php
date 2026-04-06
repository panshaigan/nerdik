<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_activity_wishes') && ! Schema::hasTable('user_activity_interests')) {
            Schema::rename('user_activity_wishes', 'user_activity_interests');
        }

        if (Schema::hasTable('user_event_wishes') && ! Schema::hasTable('user_event_interests')) {
            Schema::rename('user_event_wishes', 'user_event_interests');
        }

        if (! Schema::hasTable('event_enrollment_windows')) {
            return;
        }

        if (Schema::hasColumn('event_enrollment_windows', 'max_activities')
            && ! Schema::hasColumn('event_enrollment_windows', 'max_activities_per_user')) {
            Schema::table('event_enrollment_windows', function (Blueprint $table): void {
                $table->renameColumn('max_activities', 'max_activities_per_user');
            });
        }

        if (! Schema::hasColumn('event_enrollment_windows', 'created_by')) {
            Schema::table('event_enrollment_windows', function (Blueprint $table): void {
                $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('event_enrollment_windows', 'updated_by')) {
            $after = 'ends_at';
            if (Schema::hasColumn('event_enrollment_windows', 'updated_at')) {
                $after = 'updated_at';
            } elseif (Schema::hasColumn('event_enrollment_windows', 'max_activities_per_user')) {
                $after = 'max_activities_per_user';
            }
            Schema::table('event_enrollment_windows', function (Blueprint $table) use ($after): void {
                $table->foreignId('updated_by')->nullable()->after($after)->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_activity_interests') && ! Schema::hasTable('user_activity_wishes')) {
            Schema::rename('user_activity_interests', 'user_activity_wishes');
        }

        if (Schema::hasTable('user_event_interests') && ! Schema::hasTable('user_event_wishes')) {
            Schema::rename('user_event_interests', 'user_event_wishes');
        }

        if (! Schema::hasTable('event_enrollment_windows')) {
            return;
        }

        if (Schema::hasColumn('event_enrollment_windows', 'updated_by')) {
            Schema::table('event_enrollment_windows', function (Blueprint $table): void {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            });
        }

        if (Schema::hasColumn('event_enrollment_windows', 'created_by')) {
            Schema::table('event_enrollment_windows', function (Blueprint $table): void {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            });
        }

        if (Schema::hasColumn('event_enrollment_windows', 'max_activities_per_user')
            && ! Schema::hasColumn('event_enrollment_windows', 'max_activities')) {
            Schema::table('event_enrollment_windows', function (Blueprint $table): void {
                $table->renameColumn('max_activities_per_user', 'max_activities');
            });
        }
    }
};
