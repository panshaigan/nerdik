<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_user', function (Blueprint $table) {
            $table->dropColumn('participants_info_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_user', function (Blueprint $table) {
            $table->timestamp('participants_info_seen_at')->nullable()->after('familiarity');
        });
    }
};
