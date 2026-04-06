<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tag_translations', 'slug')) {
            Schema::table('tag_translations', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('label');
            });
        }

        $translations = DB::table('tag_translations')
            ->leftJoin('tags', 'tags.id', '=', 'tag_translations.tag_id')
            ->select('tag_translations.id', 'tag_translations.locale', 'tag_translations.label', 'tags.slug as legacy_tag_slug')
            ->orderBy('tag_translations.id')
            ->get();

        foreach ($translations as $row) {
            $locale = (string) $row->locale;
            $base = Str::slug((string) ($row->label ?: $row->legacy_tag_slug ?: 'tag'));
            $base = $base !== '' ? $base : 'tag';

            $slug = $base;
            $i = 1;
            while (DB::table('tag_translations')
                ->where('locale', $locale)
                ->where('slug', $slug)
                ->where('id', '!=', $row->id)
                ->exists()) {
                $i++;
                $slug = "{$base}-{$i}";
            }

            DB::table('tag_translations')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('tag_translations', function (Blueprint $table) {
            $table->unique(['locale', 'slug']);
        });

        if (Schema::hasColumn('tags', 'slug')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tags', 'slug')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->string('slug')->nullable()->unique()->after('category');
            });
        }

        $tags = DB::table('tags')->select('id')->orderBy('id')->get();
        foreach ($tags as $tag) {
            $preferred = DB::table('tag_translations')
                ->where('tag_id', $tag->id)
                ->where('locale', 'en')
                ->value('slug');

            $fallback = DB::table('tag_translations')
                ->where('tag_id', $tag->id)
                ->value('slug');

            $base = Str::slug((string) ($preferred ?: $fallback ?: "tag-{$tag->id}"));
            $base = $base !== '' ? $base : "tag-{$tag->id}";

            $slug = $base;
            $i = 1;
            while (DB::table('tags')->where('slug', $slug)->where('id', '!=', $tag->id)->exists()) {
                $i++;
                $slug = "{$base}-{$i}";
            }

            DB::table('tags')->where('id', $tag->id)->update(['slug' => $slug]);
        }

        if (Schema::hasColumn('tag_translations', 'slug')) {
            Schema::table('tag_translations', function (Blueprint $table) {
                $table->dropUnique(['locale', 'slug']);
                $table->dropColumn('slug');
            });
        }
    }
};
