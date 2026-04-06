<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'activity_proposal_slot',
            'activity_type_slot',
            'cities',
            'countries',
            'event_place',
            'place_slot',
            'taggables',
            'tag_aliases',
            'tag_categories',
            'tag_category_translations',
            'tag_contexts',
            'tag_relations',
            'tag_translations',
            'user_activity_wishes',
            'user_event_wishes',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $hasCreatedAt = Schema::hasColumn($table, 'created_at');
            $hasUpdatedAt = Schema::hasColumn($table, 'updated_at');

            if (! $hasCreatedAt && ! $hasUpdatedAt) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($hasCreatedAt, $hasUpdatedAt): void {
                if ($hasCreatedAt && $hasUpdatedAt) {
                    $table->dropTimestamps();

                    return;
                }

                if ($hasCreatedAt) {
                    $table->dropColumn('created_at');
                }
                if ($hasUpdatedAt) {
                    $table->dropColumn('updated_at');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'activity_proposal_slot',
            'activity_type_slot',
            'cities',
            'countries',
            'event_place',
            'place_slot',
            'taggables',
            'tag_aliases',
            'tag_categories',
            'tag_category_translations',
            'tag_contexts',
            'tag_relations',
            'tag_translations',
            'user_activity_wishes',
            'user_event_wishes',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'created_at') && ! Schema::hasColumn($table, 'updated_at')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->timestamps();
                });
            }
        }
    }
};
