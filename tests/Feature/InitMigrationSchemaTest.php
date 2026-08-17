<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class InitMigrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_squashed_application_columns_exist_without_legacy_activity_requires_approval(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['pending_email', 'is_deleted']));
        $this->assertTrue(Schema::hasColumns('organizations', [
            'logo_source',
            'logo_bg_color',
            'logo_text_color',
        ]));
        $this->assertTrue(Schema::hasColumns('user_profiles', [
            'discord_id',
            'discord_avatar_url',
            'google_email',
            'facebook_email',
            'discord_email',
            'facebook_profile_url',
            'verified_email',
            'show_contact_email',
            'show_contact_facebook',
            'show_contact_google',
            'show_contact_discord',
            'google_data',
            'facebook_data',
            'discord_data',
            'time_display_format',
        ]));
        $this->assertTrue(Schema::hasColumns('activities', [
            'participation_mode',
            'lottery_resolved_at',
            'lottery_draw_in_hours',
        ]));
        $this->assertFalse(Schema::hasColumn('activities', 'requires_approval'));
        $this->assertTrue(Schema::hasColumns('slots', [
            'requires_approval',
            'forces_participation_settings',
            'participation_mode',
            'lottery_draw_in_hours',
            'allows_observers',
        ]));
    }

    public function test_squashed_tables_exist_and_pulse_stays_on_its_own_migration(): void
    {
        $this->assertTrue(Schema::hasTable('user_requests'));
        $this->assertTrue(Schema::hasTable('activity_lottery_draws'));
        $this->assertTrue(Schema::hasTable('pulse_values'));
        $this->assertTrue(Schema::hasTable('pulse_entries'));
        $this->assertTrue(Schema::hasTable('pulse_aggregates'));

        $migrationFiles = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path): string => basename($path))
            ->values();

        $this->assertTrue($migrationFiles->contains('2026_04_29_172739_init.php'));
        $this->assertTrue($migrationFiles->contains('2026_05_14_113926_create_pulse_tables.php'));
        $this->assertFalse($migrationFiles->contains('2026_06_18_173150_create_user_requests_table.php'));
        $this->assertFalse($migrationFiles->contains('2026_07_01_161451_create_activity_lottery_draws_table.php'));
    }

    public function test_user_requests_pending_partial_unique_indexes_exist(): void
    {
        $indexNames = collect(DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'user_requests'"
        ))->pluck('indexname');

        $this->assertTrue($indexNames->contains('user_requests_pending_unique_general'));
        $this->assertTrue($indexNames->contains('user_requests_pending_organizer_flag'));
    }

    public function test_user_profile_avatar_source_allows_discord_and_rejects_unknown_values(): void
    {
        $user = User::factory()->create();

        $user->profile()->update(['avatar_source' => AvatarSource::Discord]);

        $this->assertSame(AvatarSource::Discord, $user->fresh()->profile?->avatar_source);

        $this->expectException(QueryException::class);

        DB::table('user_profiles')->where('user_id', $user->id)->update([
            'avatar_source' => 'invalid',
        ]);
    }
}
