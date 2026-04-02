<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
        });
        Schema::table('slots', function (Blueprint $table) {
            $table->dropColumn('place_id');
        });
    }

    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->foreignId('place_id')->nullable()->after('ends_at')->constrained('places')->nullOnDelete();
        });

        $pairs = DB::table('slot_place')->select('slot_id', 'place_id')->get();
        foreach ($pairs as $p) {
            DB::table('slots')->where('id', $p->slot_id)->update(['place_id' => $p->place_id]);
        }
    }
};
