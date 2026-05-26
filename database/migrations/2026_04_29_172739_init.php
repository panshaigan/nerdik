<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-file migration for all application + Laravel framework tables.
 * PostgreSQL-compatible (no unsigned, no binary collation).
 *
 * Creation order respects foreign-key dependencies.
 * The users <-> organizations circular dependency is resolved by:
 *   1. Creating organizations without user FKs
 *   2. Creating users (with organization_id FK)
 *   3. Adding user FKs to organizations in a Schema::table() call
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->ensurePolishTextSearchCatalog();

        // ------------------------------------------------------------------ //
        // 1. COUNTRIES
        // ------------------------------------------------------------------ //
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso_alpha2', 2)->unique();
        });

        // ------------------------------------------------------------------ //
        // 2. COUNTRY TRANSLATIONS
        // ------------------------------------------------------------------ //
        Schema::create('country_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('name');
            $table->unique(['country_id', 'locale']);
        });

        // ------------------------------------------------------------------ //
        // 3. CITIES
        // ------------------------------------------------------------------ //
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->nullable();
        });

        // ------------------------------------------------------------------ //
        // 4. CITY TRANSLATIONS
        // ------------------------------------------------------------------ //
        Schema::create('city_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('name');
            $table->unique(['city_id', 'locale']);
        });

        // ------------------------------------------------------------------ //
        // 5. ORGANIZATIONS  (user FKs added after users table is created)
        // ------------------------------------------------------------------ //
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('slug')->unique();
            $table->string('acronym', 12)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // FK columns declared now; constraints added below after users exist
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('deleted_by');
        });

        // ------------------------------------------------------------------ //
        // 6. USERS
        // ------------------------------------------------------------------ //
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('nickname')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_event_organizer')->default(false);
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------ //
        // 7. USER PROFILES
        // ------------------------------------------------------------------ //
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_id')->nullable();
            $table->string('facebook_id')->nullable();
            $table->string('avatar_path')->nullable();
            $table->enum('avatar_source', ['generated', 'uploaded', 'gravatar', 'google', 'facebook'])->default('generated');
            $table->string('avatar_cache_signature', 64)->nullable();
            $table->text('google_avatar_url')->nullable();
            $table->text('facebook_avatar_url')->nullable();
            $table->string('avatar_bg_color', 7)->nullable();
            $table->string('avatar_text_color', 7)->nullable();
            $table->string('avatar_initials', 3)->nullable();
            $table->string('discord_handle')->nullable();
            $table->string('current_location')->nullable();
            $table->string('timezone', 50)->nullable();
            $table->text('languages')->nullable(); // JSON stored as text (utf8mb4_bin in MySQL)
            $table->jsonb('notification_preferences')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------ //
        // 8. ADD USER FKs TO ORGANIZATIONS  (circular dep resolution)
        // ------------------------------------------------------------------ //
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });

        // ------------------------------------------------------------------ //
        // 9. PLACES
        // ------------------------------------------------------------------ //
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['venue', 'room']);
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable();
            $table->string('address')->nullable();
            $table->string('links')->nullable();
            $table->boolean('is_online')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            // Self-referential FK (parent venue -> room)
            $table->foreign('parent_id')->references('id')->on('places')->nullOnDelete();
        });

        // ------------------------------------------------------------------ //
        // 10. EVENTS
        // ------------------------------------------------------------------ //
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_public')->default(true);
            $table->string('logo_path')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('cancelled_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::statement("
            ALTER TABLE events
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('polish', coalesce(name, '')), 'A') ||
                setweight(to_tsvector('polish', coalesce(description, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX events_search_vector_idx ON events USING gin(search_vector)');

        // ------------------------------------------------------------------ //
        // 11. EVENT_PLACE  (pivot)
        // ------------------------------------------------------------------ //
        Schema::create('event_place', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->unique(['event_id', 'place_id']);
        });

        // ------------------------------------------------------------------ //
        // 12. EVENT ENROLLMENT WINDOWS
        // ------------------------------------------------------------------ //
        Schema::create('event_enrollment_windows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('max_activities_per_user')->nullable();
            $table->boolean('accumulative_activities')->default(false);
            $table->smallInteger('max_allowed_participants_per_activity')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['event_id', 'starts_at', 'ends_at'], 'event_signup_periods_event_id_starts_at_ends_at_index');
        });

        // ------------------------------------------------------------------ //
        // 13. ACTIVITY TYPES
        // ------------------------------------------------------------------ //
        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
        });

        // ------------------------------------------------------------------ //
        // 14. ACTIVITIES
        // ------------------------------------------------------------------ //
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // No ON DELETE rule in original → default RESTRICT in PG
            $table->foreignId('activity_type_id')->nullable()->constrained('activity_types');
            $table->smallInteger('hosting_mode')->default(1);
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->smallInteger('min_participants')->nullable();
            $table->smallInteger('max_participants')->nullable();
            $table->smallInteger('minimum_age')->nullable();
            $table->smallInteger('cancellation_deadline_in_hours')->nullable();
            $table->smallInteger('duration_in_minutes')->nullable();
            $table->boolean('allows_observers')->default(false);
            $table->boolean('is_host_passive')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('logo_path')->nullable();
            $table->enum('logo_source', ['tag', 'upload'])->nullable();
            $table->unsignedBigInteger('tag_media_id')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('cancelled_with_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('cancelled_at');
            $table->index('hosting_mode');
        });

        DB::statement("
            ALTER TABLE activities
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('polish', coalesce(name, '')), 'A') ||
                setweight(to_tsvector('polish', coalesce(description, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX activities_search_vector_idx ON activities USING gin(search_vector)');

        // ------------------------------------------------------------------ //
        // 15. SLOTS
        // ------------------------------------------------------------------ //
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->boolean('requires_approval')->default(false);
            $table->smallInteger('max_capacity')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // ------------------------------------------------------------------ //
        // 16. ACTIVITY_USER  (participants)
        // ------------------------------------------------------------------ //
        Schema::create('activity_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_absent')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['activity_id', 'user_id']);
        });

        // ------------------------------------------------------------------ //
        // 17. ACTIVITY WAITLIST ENTRIES
        // ------------------------------------------------------------------ //
        Schema::create('activity_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('position')->nullable();
            $table->timestamps();
            $table->unique(['activity_id', 'user_id']);
        });

        // ------------------------------------------------------------------ //
        // 18. ACTIVITY PROPOSALS
        // ------------------------------------------------------------------ //
        Schema::create('activity_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Nullable; kept after slot is decoupled from activity
            $table->foreignId('accepted_slot_id')->nullable()->constrained('slots')->nullOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->dateTime('preferred_start_time')->nullable();
            $table->timestamps();
            $table->softDeletes();
            // NOT NULL + CASCADE in original
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // ------------------------------------------------------------------ //
        // 19. ACTIVITY_PROPOSAL_SLOT  (pivot)
        // ------------------------------------------------------------------ //
        Schema::create('activity_proposal_slot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_proposal_id')->constrained('activity_proposals')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->unique(['activity_proposal_id', 'slot_id']);
        });

        // ------------------------------------------------------------------ //
        // 20. ACTIVITY_TYPE_SLOT  (pivot)
        // ------------------------------------------------------------------ //
        Schema::create('activity_type_slot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            // Nullable column, CASCADE so row is removed when type is deleted
            $table->foreignId('activity_type_id')->nullable()->constrained('activity_types')->cascadeOnDelete();
            $table->unique(['slot_id', 'activity_type_id']);
        });

        // ------------------------------------------------------------------ //
        // 21. TAG CATEGORIES
        // ------------------------------------------------------------------ //
        Schema::create('tag_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
        });

        // ------------------------------------------------------------------ //
        // 22. TAG CATEGORY TRANSLATIONS
        // ------------------------------------------------------------------ //
        Schema::create('tag_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('label', 100);
            $table->unique(['tag_category_id', 'locale']);
        });

        // ------------------------------------------------------------------ //
        // 23. TAGS
        // ------------------------------------------------------------------ //
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_category_id')->nullable()->constrained('tag_categories')->nullOnDelete();
            $table->unsignedInteger('popularity_score')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['popularity_score']);
        });

        // ------------------------------------------------------------------ //
        // 24. TAG TRANSLATIONS
        // ------------------------------------------------------------------ //
        Schema::create('tag_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label');
            $table->string('slug')->nullable();
            $table->unique(['tag_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        // ------------------------------------------------------------------ //
        // 25. TAG ALIASES
        // ------------------------------------------------------------------ //
        Schema::create('tag_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5)->nullable();
            $table->string('alias');
            $table->unique(['tag_id', 'alias', 'locale']);
        });

        // ------------------------------------------------------------------ //
        // 26. TAG RELATIONS
        // ------------------------------------------------------------------ //
        Schema::create('tag_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->foreignId('related_tag_id')->constrained('tags')->cascadeOnDelete();
            $table->unique(['tag_id', 'related_tag_id']);
        });

        // ------------------------------------------------------------------ //
        // 27. TAG CONTEXTS  (polymorphic, no FK on context)
        // ------------------------------------------------------------------ //
        Schema::create('tag_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('context');
            $table->unique(['tag_id', 'context_type', 'context_id'], 'tag_contexts_unique_idx');
            $table->index(['context_type', 'context_id'], 'tag_contexts_context_lookup_idx');
            $table->index(['tag_id', 'context_type'], 'tag_contexts_tag_type_idx');
        });

        // ------------------------------------------------------------------ //
        // 28. TAGGABLES  (polymorphic, no FK on taggable)
        // ------------------------------------------------------------------ //
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->unique(['tag_id', 'taggable_type', 'taggable_id'], 'taggables_unique_idx');
            $table->index(['taggable_type', 'taggable_id'], 'taggables_taggable_lookup_idx');
            $table->index(['tag_id', 'taggable_type'], 'taggables_tag_type_idx');
        });

        // ------------------------------------------------------------------ //
        // 28b. MEDIA  (Spatie Media Library)
        // ------------------------------------------------------------------ //
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('tag_media_id')->references('id')->on('media')->nullOnDelete();
        });

        // ------------------------------------------------------------------ //
        // 29. USER INTERESTS  (wishlist; polymorphic Activity|Event[, Tag later])
        // ------------------------------------------------------------------ //
        Schema::create('user_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('interest');
            $table->unique(['user_id', 'interest_type', 'interest_id']);
            $table->index(['interest_type', 'interest_id'], 'user_interests_interest_lookup_idx');
            $table->index(['user_id', 'interest_type'], 'user_interests_interest_type_idx');
        });

        // ------------------------------------------------------------------ //
        // 31. NOTIFICATIONS  (Laravel Notifiable; UUID primary key)
        // ------------------------------------------------------------------ //
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------ //
        // 32. NOTIFICATION EMAIL LOGS
        // ------------------------------------------------------------------ //
        Schema::create('notification_email_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('sent_at');
            $table->string('notification_type');
            $table->nullableMorphs('notifiable');
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('mailer')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index('sent_at', 'notification_email_logs_sent_at_idx');
            $table->index(['notification_type', 'sent_at'], 'notification_email_logs_type_sent_at_idx');
            $table->index(['recipient_user_id', 'sent_at'], 'notification_email_logs_user_sent_at_idx');
        });

        // ------------------------------------------------------------------ //
        // 33. SCHEDULED NOTIFICATION DISPATCHES
        // ------------------------------------------------------------------ //
        Schema::create('scheduled_notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('dispatch_date');
            $table->string('dedupe_key');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['user_id', 'dispatch_date', 'dedupe_key'], 'scheduled_notification_dispatches_unique_key');
        });

        // ================================================================== //
        // LARAVEL FRAMEWORK TABLES
        // ================================================================== //

        // ------------------------------------------------------------------ //
        // 34. PASSWORD RESET TOKENS
        // ------------------------------------------------------------------ //
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ------------------------------------------------------------------ //
        // 35. SESSIONS
        // ------------------------------------------------------------------ //
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // ------------------------------------------------------------------ //
        // 36. CACHE
        // ------------------------------------------------------------------ //
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        // ------------------------------------------------------------------ //
        // 37. CACHE LOCKS
        // ------------------------------------------------------------------ //
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        // ------------------------------------------------------------------ //
        // 38. JOBS
        // ------------------------------------------------------------------ //
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->smallInteger('attempts');
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');
            $table->index(['queue', 'reserved_at', 'available_at']);
        });

        // ------------------------------------------------------------------ //
        // 39. JOB BATCHES
        // ------------------------------------------------------------------ //
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->jsonb('failed_job_ids')->default('[]');
            $table->jsonb('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        // ------------------------------------------------------------------ //
        // 40. FAILED JOBS
        // ------------------------------------------------------------------ //
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->text('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // ------------------------------------------------------------------ //
        // 41. TELESCOPE ENTRIES
        // ------------------------------------------------------------------ //
        Schema::create('telescope_entries', function (Blueprint $table) {
            $table->bigIncrements('sequence');
            $table->uuid('uuid')->unique();
            $table->uuid('batch_id')->index();
            $table->string('family_hash')->nullable();
            $table->boolean('should_display_on_index')->default(true);
            $table->string('type', 20);
            $table->longText('content');
            $table->dateTime('created_at')->nullable();
            $table->index(['type', 'should_display_on_index']);
        });

        // ------------------------------------------------------------------ //
        // 42. TELESCOPE ENTRIES TAGS
        // ------------------------------------------------------------------ //
        Schema::create('telescope_entries_tags', function (Blueprint $table) {
            $table->uuid('entry_uuid');
            $table->string('tag');
            $table->primary(['entry_uuid', 'tag']);
            $table->index('tag');
            $table->foreign('entry_uuid')
                ->references('uuid')
                ->on('telescope_entries')
                ->cascadeOnDelete();
        });

        // ------------------------------------------------------------------ //
        // 43. TELESCOPE MONITORING
        // ------------------------------------------------------------------ //
        Schema::create('telescope_monitoring', function (Blueprint $table) {
            $table->string('tag')->primary();
        });
    }

    public function down(): void
    {
        // Drop in strict reverse dependency order

        Schema::dropIfExists('telescope_monitoring');
        Schema::dropIfExists('telescope_entries_tags');
        Schema::dropIfExists('telescope_entries');

        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');

        Schema::dropIfExists('scheduled_notification_dispatches');
        Schema::dropIfExists('notification_email_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('user_interests');
        DB::statement('DROP TABLE IF EXISTS "media" CASCADE;');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tag_contexts');
        Schema::dropIfExists('tag_relations');
        Schema::dropIfExists('tag_aliases');
        Schema::dropIfExists('tag_translations');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('tag_category_translations');
        Schema::dropIfExists('tag_categories');
        Schema::dropIfExists('activity_type_slot');
        Schema::dropIfExists('activity_proposal_slot');
        Schema::dropIfExists('activity_proposals');
        Schema::dropIfExists('activity_waitlist_entries');
        Schema::dropIfExists('activity_user');
        Schema::dropIfExists('slots');

        DB::statement('DROP INDEX IF EXISTS activities_search_vector_idx');
        Schema::dropIfExists('activities');

        Schema::dropIfExists('activity_types');
        Schema::dropIfExists('event_enrollment_windows');
        Schema::dropIfExists('event_place');

        DB::statement('DROP INDEX IF EXISTS events_search_vector_idx');
        Schema::dropIfExists('events');

        Schema::dropIfExists('places');

        // Remove user FKs from organizations before dropping users
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
        });

        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('city_translations');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('country_translations');
        Schema::dropIfExists('countries');
    }

    private function ensurePolishTextSearchCatalog(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        if (! $this->polishTextSearchDictionaryExists('polish_ispell')) {
            DB::statement('
                CREATE TEXT SEARCH DICTIONARY polish_ispell (
                    TEMPLATE = ispell,
                    DictFile = polish,
                    AffFile = polish
                )
            ');
        }

        if (! $this->polishTextSearchDictionaryExists('polish_unaccent')) {
            DB::statement('
                CREATE TEXT SEARCH DICTIONARY polish_unaccent (
                    TEMPLATE = unaccent,
                    Rules = unaccent
                )
            ');
        }

        if (! $this->polishTextSearchConfigurationExists()) {
            DB::statement('CREATE TEXT SEARCH CONFIGURATION polish (COPY = simple)');
        }

        DB::statement('
            ALTER TEXT SEARCH CONFIGURATION polish
                ALTER MAPPING FOR asciiword, asciihword, hword_asciipart,
                                  word, hword, hword_part
                WITH polish_ispell, polish_unaccent, simple
        ');
    }

    private function polishTextSearchDictionaryExists(string $name): bool
    {
        return (bool) DB::selectOne(
            'SELECT EXISTS (SELECT 1 FROM pg_catalog.pg_ts_dict WHERE dictname = ?) AS exists',
            [$name]
        )->exists;
    }

    private function polishTextSearchConfigurationExists(): bool
    {
        return (bool) DB::selectOne(
            "SELECT EXISTS (SELECT 1 FROM pg_catalog.pg_ts_config WHERE cfgname = 'polish') AS exists"
        )->exists;
    }
};
