<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_play_areas', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('label')->constrained()->nullOnDelete();
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('location_label')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_play_areas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
