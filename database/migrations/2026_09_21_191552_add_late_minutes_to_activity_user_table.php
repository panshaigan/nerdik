<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_user', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_minutes')->nullable()->after('is_absent');
        });
    }

    public function down(): void
    {
        Schema::table('activity_user', function (Blueprint $table) {
            $table->dropColumn('late_minutes');
        });
    }
};
