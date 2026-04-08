<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('slots')) {
            return;
        }

        if (! Schema::hasColumn('slots', 'place_id')) {
            Schema::table('slots', function (Blueprint $table): void {
                $table->foreignId('place_id')->nullable()->after('activity_id')->constrained('places')->nullOnDelete();
            });
        }

        if (Schema::hasTable('place_slot')) {
            DB::statement('UPDATE slots s JOIN (SELECT slot_id, MIN(place_id) AS place_id FROM place_slot GROUP BY slot_id) ps ON ps.slot_id = s.id SET s.place_id = ps.place_id WHERE s.place_id IS NULL');
            Schema::drop('place_slot');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('slots') && ! Schema::hasTable('place_slot')) {
            Schema::create('place_slot', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('place_id')->constrained()->cascadeOnDelete();
                $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['place_id', 'slot_id']);
            });
        }

        if (Schema::hasTable('slots') && Schema::hasColumn('slots', 'place_id')) {
            DB::statement('INSERT IGNORE INTO place_slot (place_id, slot_id, created_at, updated_at) SELECT place_id, id, NOW(), NOW() FROM slots WHERE place_id IS NOT NULL');
            Schema::table('slots', function (Blueprint $table): void {
                $table->dropForeign(['place_id']);
                $table->dropColumn('place_id');
            });
        }
    }
};
