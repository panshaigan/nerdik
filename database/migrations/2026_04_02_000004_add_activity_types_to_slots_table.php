<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('slots', 'activity_types')) {
            Schema::table('slots', function (Blueprint $table) {
                $table->json('activity_types')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('slots', 'activity_types')) {
            Schema::table('slots', function (Blueprint $table) {
                $table->dropColumn('activity_types');
            });
        }
    }
};
