<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('slots')
            ->whereNotNull('place_id')
            ->select('id as slot_id', 'place_id', 'created_at', 'updated_at')
            ->get();

        foreach ($rows as $row) {
            DB::table('slot_place')->insert([
                'slot_id' => $row->slot_id,
                'place_id' => $row->place_id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        $pairs = DB::table('slot_place')->select('slot_id', 'place_id')->get();
        foreach ($pairs as $p) {
            DB::table('slots')->where('id', $p->slot_id)->update(['place_id' => $p->place_id]);
        }
        DB::table('slot_place')->truncate();
    }
};
