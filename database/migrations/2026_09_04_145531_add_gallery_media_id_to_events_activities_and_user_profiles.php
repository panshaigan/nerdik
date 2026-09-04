<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('gallery_media_id')
                ->nullable()
                ->after('listing_media_id')
                ->constrained('media')
                ->nullOnDelete();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('gallery_media_id')
                ->nullable()
                ->after('tag_media_id')
                ->constrained('media')
                ->nullOnDelete();
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('gallery_media_id')
                ->nullable()
                ->after('avatar_source')
                ->constrained('media')
                ->nullOnDelete();
        });

        DB::statement('ALTER TABLE user_profiles DROP CONSTRAINT IF EXISTS user_profiles_avatar_source_check');
        DB::statement("ALTER TABLE user_profiles ADD CONSTRAINT user_profiles_avatar_source_check CHECK ((avatar_source)::text = ANY ((ARRAY['generated'::character varying, 'uploaded'::character varying, 'gravatar'::character varying, 'google'::character varying, 'facebook'::character varying, 'discord'::character varying, 'gallery'::character varying])::text[]))");

        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_logo_source_check');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_logo_source_check CHECK ((logo_source)::text = ANY ((ARRAY['default'::character varying, 'gallery'::character varying, 'upload'::character varying])::text[]) OR logo_source IS NULL)");

        DB::statement('ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_logo_source_check');
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_logo_source_check CHECK ((logo_source)::text = ANY ((ARRAY['tag'::character varying, 'gallery'::character varying, 'upload'::character varying])::text[]) OR logo_source IS NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gallery_media_id');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gallery_media_id');
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gallery_media_id');
        });

        DB::statement('ALTER TABLE user_profiles DROP CONSTRAINT IF EXISTS user_profiles_avatar_source_check');
        DB::statement("ALTER TABLE user_profiles ADD CONSTRAINT user_profiles_avatar_source_check CHECK ((avatar_source)::text = ANY ((ARRAY['generated'::character varying, 'uploaded'::character varying, 'gravatar'::character varying, 'google'::character varying, 'facebook'::character varying, 'discord'::character varying])::text[]))");

        DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_logo_source_check');
        DB::statement("ALTER TABLE events ADD CONSTRAINT events_logo_source_check CHECK ((logo_source)::text = ANY ((ARRAY['default'::character varying, 'upload'::character varying])::text[]) OR logo_source IS NULL)");

        DB::statement('ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_logo_source_check');
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_logo_source_check CHECK ((logo_source)::text = ANY ((ARRAY['tag'::character varying, 'upload'::character varying])::text[]) OR logo_source IS NULL)");
    }
};
