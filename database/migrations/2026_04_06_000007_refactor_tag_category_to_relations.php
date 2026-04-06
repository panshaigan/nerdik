<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tag_categories')) {
            Schema::create('tag_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 50)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tag_category_translations')) {
            Schema::create('tag_category_translations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tag_category_id')->constrained('tag_categories')->cascadeOnDelete();
                $table->string('locale', 10);
                $table->string('label', 100);
                $table->timestamps();
                $table->unique(['tag_category_id', 'locale']);
            });
        }

        $defaultKeys = ['game', 'publisher', 'world', 'convention', 'engine', 'trigger', 'block', 'misc'];
        $existingKeys = Schema::hasColumn('tags', 'category')
            ? DB::table('tags')->select('category')->distinct()->pluck('category')->filter()->map(fn ($k) => mb_strtolower(trim((string) $k)))->all()
            : [];
        $allKeys = array_values(array_unique(array_merge($defaultKeys, $existingKeys)));

        foreach ($allKeys as $key) {
            $id = DB::table('tag_categories')->where('key', $key)->value('id');
            if (! $id) {
                $id = DB::table('tag_categories')->insertGetId([
                    'key' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $label = ucfirst($key);
            DB::table('tag_category_translations')->updateOrInsert(
                ['tag_category_id' => $id, 'locale' => 'en'],
                ['label' => $label, 'updated_at' => now(), 'created_at' => now()]
            );
            DB::table('tag_category_translations')->updateOrInsert(
                ['tag_category_id' => $id, 'locale' => 'pl'],
                ['label' => $label, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (! Schema::hasColumn('tags', 'tag_category_id')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->foreignId('tag_category_id')->nullable()->after('id')->constrained('tag_categories')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('tags', 'category')) {
            $map = DB::table('tag_categories')->pluck('id', 'key');
            $rows = DB::table('tags')->select(['id', 'category'])->get();
            foreach ($rows as $row) {
                $key = mb_strtolower(trim((string) ($row->category ?? '')));
                $categoryId = $map[$key] ?? $map['misc'] ?? null;
                if ($categoryId !== null) {
                    DB::table('tags')->where('id', $row->id)->update(['tag_category_id' => $categoryId]);
                }
            }

            Schema::table('tags', function (Blueprint $table): void {
                $table->dropColumn('category');
            });
        }

        Schema::table('tags', function (Blueprint $table): void {
            $table->index('tag_category_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tags', 'category')) {
            Schema::table('tags', function (Blueprint $table): void {
                $table->string('category', 50)->nullable()->after('id');
            });
        }

        if (Schema::hasColumn('tags', 'tag_category_id')) {
            $categories = DB::table('tag_categories')->pluck('key', 'id');
            $rows = DB::table('tags')->select(['id', 'tag_category_id'])->get();
            foreach ($rows as $row) {
                $key = $categories[$row->tag_category_id] ?? 'misc';
                DB::table('tags')->where('id', $row->id)->update(['category' => $key]);
            }

            Schema::table('tags', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tag_category_id');
            });
        }

        if (Schema::hasTable('tag_category_translations')) {
            Schema::drop('tag_category_translations');
        }
        if (Schema::hasTable('tag_categories')) {
            Schema::drop('tag_categories');
        }
    }
};
