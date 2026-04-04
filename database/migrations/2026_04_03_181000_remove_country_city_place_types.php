<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Countries and cities live in `countries` / `cities`; places are only regional/physical (state, venue, room).
     */
    public function up(): void
    {
        DB::table('places')->where('type', 'country')->update([
            'type' => 'venue',
            'parent_id' => null,
        ]);

        DB::table('places')->where('type', 'city')->update([
            'type' => 'venue',
            'parent_id' => null,
        ]);

        if (! $this->isMysql()) {
            return;
        }

        DB::statement("ALTER TABLE places MODIFY COLUMN type ENUM('state','venue','room') NOT NULL");
    }

    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        DB::statement("ALTER TABLE places MODIFY COLUMN type ENUM('country','state','city','venue','room') NOT NULL");
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
