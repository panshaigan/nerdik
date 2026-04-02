<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('slots', 'activity_types')) {
            // Migrate the old JSON column into the join table before dropping it.
            if (Schema::hasTable('slot_activity_type')) {
                $now = now();

                DB::table('slots')
                    ->select(['id', 'activity_types'])
                    ->whereNotNull('activity_types')
                    ->orderBy('id')
                    ->chunk(200, function ($rows) use ($now) {
                        $insert = [];
                        foreach ($rows as $row) {
                            $types = json_decode((string) $row->activity_types, true);
                            if (! is_array($types)) {
                                continue;
                            }
                            foreach ($types as $t) {
                                if (! is_string($t) || trim($t) === '') {
                                    continue;
                                }
                                $insert[] = [
                                    'slot_id' => (int) $row->id,
                                    'activity_type' => $t,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                        }

                        if (! empty($insert)) {
                            DB::table('slot_activity_type')->insertOrIgnore($insert);
                        }
                    });
            }

            Schema::table('slots', function (Blueprint $table) {
                $table->dropColumn('activity_types');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('slots', 'activity_types')) {
            Schema::table('slots', function (Blueprint $table) {
                $table->json('activity_types')->nullable();
            });
        }
    }
};
