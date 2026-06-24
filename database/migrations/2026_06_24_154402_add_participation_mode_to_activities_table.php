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
            $table->string('participation_mode', 32)->default('open')->after('updated_by');
            $table->timestamp('lottery_resolved_at')->nullable()->after('participation_mode');
        });

        DB::table('activities')
            ->where('requires_approval', true)
            ->update(['participation_mode' => 'host_approval']);

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('requires_approval');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('updated_by');
        });

        DB::table('activities')
            ->where('participation_mode', 'host_approval')
            ->update(['requires_approval' => true]);

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['participation_mode', 'lottery_resolved_at']);
        });
    }
};
