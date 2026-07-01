<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->boolean('forces_participation_settings')->default(false)->after('requires_approval');
            $table->string('participation_mode')->nullable()->after('forces_participation_settings');
            $table->unsignedSmallInteger('lottery_draw_in_hours')->nullable()->after('participation_mode');
            $table->boolean('allows_observers')->nullable()->after('lottery_draw_in_hours');
        });
    }

    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->dropColumn([
                'forces_participation_settings',
                'participation_mode',
                'lottery_draw_in_hours',
                'allows_observers',
            ]);
        });
    }
};
