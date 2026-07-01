<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->smallInteger('lottery_draw_in_hours')->nullable()->after('cancellation_deadline_in_hours');
        });

        DB::table('activities')
            ->where('participation_mode', 'lottery')
            ->whereNotNull('cancellation_deadline_in_hours')
            ->update([
                'lottery_draw_in_hours' => DB::raw('cancellation_deadline_in_hours'),
            ]);
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('lottery_draw_in_hours');
        });
    }
};
