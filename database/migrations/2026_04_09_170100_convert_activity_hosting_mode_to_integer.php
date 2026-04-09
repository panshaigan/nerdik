<?php

use App\Models\Activity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_TO_INT = [
        'draft' => Activity::HOSTING_MODE_DRAFT,
        'self_hosted' => Activity::HOSTING_MODE_SELF_HOSTED,
        'proposed_to_event' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
        'scheduled_on_event' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('activities') || ! Schema::hasColumn('activities', 'hosting_mode')) {
            return;
        }

        DB::table('activities')
            ->select(['id', 'hosting_mode'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $raw = $row->hosting_mode;
                    $next = $this->normalizeHostingMode($raw);
                    DB::table('activities')
                        ->where('id', $row->id)
                        ->update(['hosting_mode' => $next]);
                }
            });

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Keep the same column name; switch storage type to TINYINT UNSIGNED.
            // SQLite keeps dynamic typing and is exercised in tests.
            try {
                DB::statement('ALTER TABLE activities DROP INDEX activities_hosting_mode_index');
            } catch (Throwable) {
                // index may not exist in some installations
            }
            DB::statement('ALTER TABLE activities MODIFY hosting_mode TINYINT UNSIGNED NOT NULL DEFAULT '.Activity::HOSTING_MODE_DRAFT);
            try {
                DB::statement('ALTER TABLE activities ADD INDEX activities_hosting_mode_index (hosting_mode)');
            } catch (Throwable) {
                // keep migration idempotent when index already exists
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activities') || ! Schema::hasColumn('activities', 'hosting_mode')) {
            return;
        }

        DB::table('activities')
            ->select(['id', 'hosting_mode'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $raw = (int) $row->hosting_mode;
                    $legacy = match ($raw) {
                        Activity::HOSTING_MODE_SELF_HOSTED => 'self_hosted',
                        Activity::HOSTING_MODE_PROPOSED_TO_EVENT => 'proposed_to_event',
                        Activity::HOSTING_MODE_SCHEDULED_ON_EVENT => 'scheduled_on_event',
                        default => 'draft',
                    };
                    DB::table('activities')
                        ->where('id', $row->id)
                        ->update(['hosting_mode' => $legacy]);
                }
            });

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('ALTER TABLE activities DROP INDEX activities_hosting_mode_index');
            } catch (Throwable) {
                // index may not exist in some installations
            }
            DB::statement("ALTER TABLE activities MODIFY hosting_mode VARCHAR(32) NOT NULL DEFAULT 'draft'");
            try {
                DB::statement('ALTER TABLE activities ADD INDEX activities_hosting_mode_index (hosting_mode)');
            } catch (Throwable) {
                // keep migration idempotent when index already exists
            }
        }
    }

    private function normalizeHostingMode(mixed $raw): int
    {
        if (is_int($raw)) {
            return $this->intOrDefault($raw);
        }
        if (is_numeric($raw)) {
            return $this->intOrDefault((int) $raw);
        }

        $str = strtolower(trim((string) $raw));

        return self::LEGACY_TO_INT[$str] ?? Activity::HOSTING_MODE_DRAFT;
    }

    private function intOrDefault(int $raw): int
    {
        if (in_array($raw, Activity::hostingModes(), true)) {
            return $raw;
        }

        return Activity::HOSTING_MODE_DRAFT;
    }
};
