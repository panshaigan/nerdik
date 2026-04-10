<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_enrollment_windows')) {
            return;
        }

        Schema::table('event_enrollment_windows', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_enrollment_windows', 'max_allowed_participants_per_activity')) {
                $table->unsignedSmallInteger('max_allowed_participants_per_activity')
                    ->nullable()
                    ->after('max_activities_per_user');
            }
            if (! Schema::hasColumn('event_enrollment_windows', 'accumulative_activities')) {
                $table->boolean('accumulative_activities')
                    ->default(false)
                    ->after('max_allowed_participants_per_activity');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_enrollment_windows')) {
            return;
        }

        Schema::table('event_enrollment_windows', function (Blueprint $table): void {
            if (Schema::hasColumn('event_enrollment_windows', 'accumulative_activities')) {
                $table->dropColumn('accumulative_activities');
            }
            if (Schema::hasColumn('event_enrollment_windows', 'max_allowed_participants_per_activity')) {
                $table->dropColumn('max_allowed_participants_per_activity');
            }
        });
    }
};
