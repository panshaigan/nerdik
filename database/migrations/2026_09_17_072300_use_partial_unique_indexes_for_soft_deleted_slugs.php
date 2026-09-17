<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'organizations',
        'places',
        'events',
        'activities',
        'event_series',
    ];

    /**
     * Replace full unique slug constraints with live-row-only partial unique indexes
     * so soft-deleted rows do not block slug reuse.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Older schema used a UNIQUE constraint; create migrations may already
            // have a partial unique index under the same name.
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_slug_unique");
            DB::statement("DROP INDEX IF EXISTS {$table}_slug_unique");
            DB::statement(
                "CREATE UNIQUE INDEX {$table}_slug_unique ON {$table} (slug) WHERE deleted_at IS NULL"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP INDEX IF EXISTS {$table}_slug_unique");

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique('slug');
            });
        }
    }
};
