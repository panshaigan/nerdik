<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('slots', 'activity_id')) {
            Schema::table('slots', function (Blueprint $table) {
                $table->foreignId('activity_id')->nullable()->after('max_capacity')->constrained('activities')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('slots', 'activity_id')) {
            Schema::table('slots', function (Blueprint $table) {
                $table->dropForeign(['activity_id']);
            });
        }
    }
};
