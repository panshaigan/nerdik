<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS user_requests_pending_organizer_flag');
        DB::statement("
            CREATE UNIQUE INDEX user_requests_pending_organizer_flag
            ON user_requests (requester_id)
            WHERE status = 'pending' AND recipient_id IS NULL AND type = 'event_organizer_flag'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS user_requests_pending_organizer_flag');
        DB::statement("
            CREATE UNIQUE INDEX user_requests_pending_organizer_flag
            ON user_requests (type, requester_id)
            WHERE status = 'pending' AND recipient_id IS NULL
        ");
    }
};
