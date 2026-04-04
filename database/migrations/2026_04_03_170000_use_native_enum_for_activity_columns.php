<?php

use App\Enums\ActivityProposalStatus;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL/MariaDB: store these columns as native ENUM. SQLite (e.g. tests) keeps VARCHAR; app-level casts still apply.
     */
    public function up(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        $activityTypes = $this->quotedEnumList(ActivityType::values());
        $activityStatuses = $this->quotedEnumList(ActivityStatus::values());
        $proposalStatuses = $this->quotedEnumList(ActivityProposalStatus::values());

        DB::statement("ALTER TABLE activities MODIFY COLUMN status ENUM({$activityStatuses}) NOT NULL DEFAULT 'planned'");
        DB::statement("ALTER TABLE activities MODIFY COLUMN type ENUM({$activityTypes}) NOT NULL");
        DB::statement("ALTER TABLE activity_proposals MODIFY COLUMN status ENUM({$proposalStatuses}) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE activity_type_slot MODIFY COLUMN activity_type ENUM({$activityTypes}) NOT NULL");
    }

    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        DB::statement("ALTER TABLE activities MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT 'planned'");
        DB::statement('ALTER TABLE activities MODIFY COLUMN type VARCHAR(255) NOT NULL');
        DB::statement("ALTER TABLE activity_proposals MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE activity_type_slot MODIFY COLUMN activity_type VARCHAR(50) NOT NULL');
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedEnumList(array $values): string
    {
        return implode(',', array_map(fn (string $v) => "'".str_replace("'", "''", $v)."'", $values));
    }
};
