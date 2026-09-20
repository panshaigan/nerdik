<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_user', function (Blueprint $table) {
            $table->jsonb('familiarity')->nullable()->after('is_absent');
        });

        Schema::table('activity_waitlist_entries', function (Blueprint $table) {
            $table->jsonb('familiarity')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('activity_user', function (Blueprint $table) {
            $table->dropColumn('familiarity');
        });

        Schema::table('activity_waitlist_entries', function (Blueprint $table) {
            $table->dropColumn('familiarity');
        });
    }
};
