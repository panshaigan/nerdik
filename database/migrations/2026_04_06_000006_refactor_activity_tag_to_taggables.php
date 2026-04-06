<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->string('taggable_type', 100);
                $table->unsignedBigInteger('taggable_id');
                $table->timestamps();

                // Fast reverse lookup: entity -> tags.
                $table->index(['taggable_type', 'taggable_id'], 'taggables_taggable_lookup_idx');
                // Fast tag-centric lookup with type filter.
                $table->index(['tag_id', 'taggable_type'], 'taggables_tag_type_idx');
                // Prevent duplicates and support tag -> entities lookup.
                $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique_idx');
            });
        }

        if (Schema::hasTable('activity_tag')) {
            $rows = DB::table('activity_tag')
                ->select(['tag_id', 'activity_id', 'created_at', 'updated_at'])
                ->get();

            if ($rows->isNotEmpty()) {
                $now = now();
                $payload = $rows->map(static function ($row) use ($now): array {
                    return [
                        'tag_id' => (int) $row->tag_id,
                        'taggable_type' => 'activity',
                        'taggable_id' => (int) $row->activity_id,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                })->all();

                DB::table('taggables')->upsert(
                    $payload,
                    ['tag_id', 'taggable_type', 'taggable_id'],
                    ['updated_at']
                );
            }

            Schema::drop('activity_tag');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_tag')) {
            Schema::create('activity_tag', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['activity_id', 'tag_id']);
            });
        }

        if (Schema::hasTable('taggables')) {
            $rows = DB::table('taggables')
                ->whereIn('taggable_type', ['activity', 'App\\Models\\Activity'])
                ->select(['tag_id', 'taggable_id', 'created_at', 'updated_at'])
                ->get();

            if ($rows->isNotEmpty()) {
                $payload = $rows->map(static fn ($row): array => [
                    'activity_id' => (int) $row->taggable_id,
                    'tag_id' => (int) $row->tag_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ])->all();

                DB::table('activity_tag')->upsert(
                    $payload,
                    ['activity_id', 'tag_id'],
                    ['updated_at']
                );
            }

            Schema::drop('taggables');
        }
    }
};
