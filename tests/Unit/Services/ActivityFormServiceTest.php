<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\LotteryDrawTrigger;
use App\Enums\ParticipationMode;
use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityLotteryDraw;
use App\Models\ActivityType;
use App\Models\Place;
use App\Models\User;
use App\Services\ActivityFormService;
use App\Services\ActivityHostingModeService;
use App\Services\LocationResolver;
use App\Services\TagSelectionService;
use App\Support\Performance\PersistenceTiming;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class ActivityFormServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_persist_defaults_invalid_participation_mode_to_open_on_create(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        /** @var ManageActivityForm $form */
        $form = Livewire::actingAs($user)->test(ManageActivityForm::class)->instance();
        $form->hosting_mode = Activity::HOSTING_MODE_DRAFT;
        $form->tag_ids = [];
        $form->new_tags = [];

        $validated = $this->draftValidatedPayload(new Activity([
            'name' => 'Service Coerced Mode',
            'activity_type_id' => $activityTypeId,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]), [
            'participation_mode' => 'not-a-real-mode',
        ]);

        app(ActivityFormService::class)->persist(
            $form,
            $validated,
            app(TagSelectionService::class),
            app(ActivityHostingModeService::class),
            app(LocationResolver::class),
            (new PersistenceTiming)->start('test'),
        );

        $activity = Activity::query()->where('name', 'Service Coerced Mode')->first();

        $this->assertNotNull($activity);
        $this->assertSame(ParticipationMode::Open, $activity->participation_mode);
    }

    public function test_persist_clears_lottery_state_when_participation_mode_changes_on_update(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'participation_mode' => ParticipationMode::Lottery,
            'lottery_draw_in_hours' => 24,
            'lottery_resolved_at' => now(),
        ]);
        ActivityLotteryDraw::query()->create([
            'activity_id' => $activity->id,
            'trigger' => LotteryDrawTrigger::Final,
            'drawn_at' => now(),
        ]);

        /** @var ManageActivityForm $form */
        $form = Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->instance();
        $form->tag_ids = [];
        $form->new_tags = [];

        $validated = $this->draftValidatedPayload($activity, [
            'participation_mode' => ParticipationMode::Open->value,
            'lottery_draw_in_hours' => null,
        ]);

        app(ActivityFormService::class)->persist(
            $form,
            $validated,
            app(TagSelectionService::class),
            app(ActivityHostingModeService::class),
            app(LocationResolver::class),
            (new PersistenceTiming)->start('test'),
        );

        $fresh = $activity->fresh();

        $this->assertSame(ParticipationMode::Open, $fresh->participation_mode);
        $this->assertNull($fresh->lottery_resolved_at);
        $this->assertFalse(
            ActivityLotteryDraw::query()->where('activity_id', $activity->id)->exists()
        );
    }

    public function test_persist_self_hosted_creates_room_under_selected_venue(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;
        $venue = Place::create([
            'name' => 'Self Host Venue',
            'type' => 'venue',
            'is_online' => false,
        ]);

        /** @var ManageActivityForm $form */
        $form = Livewire::actingAs($user)->test(ManageActivityForm::class)->instance();
        $form->hosting_mode = Activity::HOSTING_MODE_SELF_HOSTED;
        $form->self_hosted_venue_place_id = $venue->id;
        $form->self_hosted_room_name = 'Table 3';
        $form->self_hosted_starts_at = now()->addDay()->startOfHour()->format('Y-m-d\TH:i');
        $form->tag_ids = [];
        $form->new_tags = [];
        $form->new_places = [];

        $validated = $this->draftValidatedPayload(new Activity([
            'name' => 'Self Hosted Room Activity',
            'activity_type_id' => $activityTypeId,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'duration_in_minutes' => 120,
        ]), [
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open->value,
        ]);

        app(ActivityFormService::class)->persist(
            $form,
            $validated,
            app(TagSelectionService::class),
            app(ActivityHostingModeService::class),
            app(LocationResolver::class),
            (new PersistenceTiming)->start('test'),
        );

        $activity = Activity::query()->where('name', 'Self Hosted Room Activity')->first();
        $this->assertNotNull($activity);
        $this->assertSame(Activity::HOSTING_MODE_SELF_HOSTED, $activity->hosting_mode);
        $this->assertNotNull($activity->place_id);

        $room = Place::query()->find($activity->place_id);
        $this->assertNotNull($room);
        $this->assertSame('room', $room->type);
        $this->assertSame($venue->id, (int) $room->parent_id);
        $this->assertSame('Table 3', $room->name);
    }

    public function test_persist_self_hosted_requires_place_and_start(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        /** @var ManageActivityForm $form */
        $form = Livewire::actingAs($user)->test(ManageActivityForm::class)->instance();
        $form->hosting_mode = Activity::HOSTING_MODE_SELF_HOSTED;
        $form->self_hosted_venue_place_id = null;
        $form->self_hosted_place_id = null;
        $form->self_hosted_starts_at = null;
        $form->tag_ids = [];
        $form->new_tags = [];

        $validated = $this->draftValidatedPayload(new Activity([
            'name' => 'Missing Self Host Fields',
            'activity_type_id' => $activityTypeId,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]), [
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $this->expectException(ValidationException::class);
        app(ActivityFormService::class)->persist(
            $form,
            $validated,
            app(TagSelectionService::class),
            app(ActivityHostingModeService::class),
            app(LocationResolver::class),
            (new PersistenceTiming)->start('test'),
        );
    }

    public function test_persist_self_hosted_uses_venue_when_no_room_name(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;
        $venue = Place::create([
            'name' => 'Venue Only Place',
            'type' => 'venue',
            'is_online' => false,
        ]);

        /** @var ManageActivityForm $form */
        $form = Livewire::actingAs($user)->test(ManageActivityForm::class)->instance();
        $form->hosting_mode = Activity::HOSTING_MODE_SELF_HOSTED;
        $form->self_hosted_venue_place_id = $venue->id;
        $form->self_hosted_room_name = null;
        $form->self_hosted_starts_at = now()->addDays(2)->startOfHour()->format('Y-m-d\TH:i');
        $form->tag_ids = [];
        $form->new_tags = [];
        $form->new_places = [];

        $validated = $this->draftValidatedPayload(new Activity([
            'name' => 'Venue Only Activity',
            'activity_type_id' => $activityTypeId,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'duration_in_minutes' => 60,
        ]), [
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open->value,
        ]);

        app(ActivityFormService::class)->persist(
            $form,
            $validated,
            app(TagSelectionService::class),
            app(ActivityHostingModeService::class),
            app(LocationResolver::class),
            (new PersistenceTiming)->start('test'),
        );

        $activity = Activity::query()->where('name', 'Venue Only Activity')->first();
        $this->assertNotNull($activity);
        $this->assertSame($venue->id, (int) $activity->place_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function draftValidatedPayload(Activity $activity, array $overrides = []): array
    {
        $participationMode = $activity->participation_mode;
        if ($participationMode instanceof ParticipationMode) {
            $participationMode = $participationMode->value;
        }

        return array_merge([
            'name' => $activity->name,
            'description' => $activity->description,
            'activity_type_id' => $activity->activity_type_id,
            'activity_series_id' => $activity->activity_series_id,
            'organization_id' => $activity->organization_id,
            'min_participants' => $activity->min_participants,
            'max_participants' => $activity->max_participants,
            'minimum_age' => $activity->minimum_age,
            'maximum_age' => $activity->maximum_age,
            'duration_in_minutes' => $activity->duration_in_minutes,
            'cancellation_deadline_in_hours' => $activity->cancellation_deadline_in_hours,
            'lottery_draw_in_hours' => $activity->lottery_draw_in_hours,
            'participation_mode' => $participationMode,
            'allows_observers' => (bool) $activity->allows_observers,
            'collect_familiarity' => (bool) $activity->collect_familiarity,
            'is_host_passive' => (bool) $activity->is_host_passive,
            'hosting_mode' => $activity->hosting_mode,
            'price' => $activity->price,
        ], $overrides);
    }
}
