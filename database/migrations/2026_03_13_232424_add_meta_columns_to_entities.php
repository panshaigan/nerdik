<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add created_by, updated_by for ownership/audit; deleted_at, deleted_by for soft deletes.
     * All datetime columns (starts_at, ends_at, etc.) are stored in UTC.
     */
    public function up(): void
    {
        $meta = function (Blueprint $table) {
            if (! Schema::hasColumn($table->getTable(), 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn($table->getTable(), 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
            }
        };

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('event_instances', 'created_by')) {
            Schema::table('event_instances', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('event_id')->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
                $table->timestamp('deleted_at')->nullable()->after('updated_by');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('organizations', 'created_by')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('slots', 'created_by')) {
            Schema::table('slots', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('event_instance_id')->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('places', 'created_by')) {
            Schema::table('places', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('tags', 'created_by')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('activities', 'created_by')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('host_user_id')->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('activity_proposals', 'updated_by')) {
            Schema::table('activity_proposals', function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('activity_proposals', 'deleted_at')) {
            Schema::table('activity_proposals', function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('events', 'updated_by')) {
            Schema::table('events', function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->after('updated_at')->constrained('users')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('events', 'deleted_at')) {
            Schema::table('events', function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'events' => ['deleted_at', 'deleted_by', 'updated_by'],
            'event_instances' => ['deleted_by', 'deleted_at', 'updated_by', 'created_by'],
            'organizations' => ['deleted_by', 'deleted_at', 'updated_by', 'created_by'],
            'slots' => ['deleted_by', 'deleted_at', 'updated_by', 'created_by'],
            'places' => ['deleted_by', 'deleted_at', 'updated_by', 'created_by'],
            'tags' => ['deleted_by', 'deleted_at', 'updated_by', 'created_by'],
            'activities' => ['deleted_by', 'deleted_at', 'updated_by', 'created_by'],
            'activity_proposals' => ['deleted_by', 'deleted_at', 'updated_by'],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach (['deleted_by', 'updated_by', 'created_by'] as $fk) {
                    if (in_array($fk, $columns, true) && Schema::hasColumn($table, $fk)) {
                        $t->dropForeign([$fk]);
                    }
                }
                foreach ($columns as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
