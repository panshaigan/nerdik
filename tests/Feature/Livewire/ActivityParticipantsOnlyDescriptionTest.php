<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\ParticipationMode;
use App\Livewire\Activities\ManageActivityForm;
use App\Livewire\Activities\ShowActivity;
use App\Livewire\Browse\BrowseActivities;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityParticipantsOnlyDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVATE_MARKUP = '<p>Participants-only corridor code</p>';

    #[Test]
    public function host_can_save_participants_only_description(): void
    {
        $host = User::factory()->create();
        $rpg = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $rpg->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $this->actingAs($host);

        Livewire::test(ManageActivityForm::class, ['activity' => $activity])
            ->set('tab', 'minor-details')
            ->set('participants_only_description', self::PRIVATE_MARKUP)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('activities.show', $activity));

        $activity->refresh();
        $this->assertStringContainsString('Participants-only corridor code', (string) $activity->participants_only_description);
    }

    #[Test]
    public function participants_only_description_visible_to_participant_and_host_but_not_stranger(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $stranger = User::factory()->create();

        $activity = Activity::factory()->selfHosted()->create([
            'created_by' => $host->id,
            'participants_only_description' => self::PRIVATE_MARKUP,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
        ]);

        $marker = 'data-ui="activity-show-participants-only-description"';

        $guestHtml = Livewire::test(ShowActivity::class, ['activity' => $activity])->html();
        $this->assertStringNotContainsString($marker, $guestHtml);

        $strangerHtml = Livewire::actingAs($stranger)->test(ShowActivity::class, ['activity' => $activity])->html();
        $this->assertStringNotContainsString($marker, $strangerHtml);

        $participantHtml = Livewire::actingAs($participant)->test(ShowActivity::class, ['activity' => $activity])->html();
        $this->assertStringContainsString($marker, $participantHtml);
        $this->assertStringContainsString('Participants-only corridor code', $participantHtml);

        $hostHtml = Livewire::actingAs($host)->test(ShowActivity::class, ['activity' => $activity])->html();
        $this->assertStringContainsString($marker, $hostHtml);
    }

    #[Test]
    public function join_opens_participants_info_modal_and_switches_to_info_tab(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
            'participants_only_description' => self::PRIVATE_MARKUP,
        ]);

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertSet('participantsInfoModalOpen', false)
            ->call('join')
            ->assertSet('participantsInfoModalOpen', true)
            ->assertSet('tab', 'info');

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertSet('participantsInfoModalOpen', false);
    }

    #[Test]
    public function existing_participant_does_not_get_modal_on_page_load(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $activity = Activity::factory()->selfHosted()->create([
            'created_by' => $host->id,
            'participants_only_description' => self::PRIVATE_MARKUP,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
        ]);

        Livewire::actingAs($participant)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertSet('participantsInfoModalOpen', false);
    }

    #[Test]
    public function join_without_participants_only_text_does_not_open_modal(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
            'participants_only_description' => null,
        ]);

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join')
            ->assertSet('participantsInfoModalOpen', false);
    }

    #[Test]
    public function join_from_listing_preview_opens_participants_info_modal(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
            'participants_only_description' => self::PRIVATE_MARKUP,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($member)
            ->test(BrowseActivities::class)
            ->call('openListingActivityPreview', $activity->id)
            ->call('joinPreviewActivity')
            ->assertSet('participantsInfoModalOpen', true)
            ->assertSet('activityPreviewTab', 'info');
    }

    #[Test]
    public function host_not_on_roster_does_not_get_participants_info_modal(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->selfHosted()->create([
            'created_by' => $host->id,
            'participants_only_description' => self::PRIVATE_MARKUP,
        ]);

        Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertSet('participantsInfoModalOpen', false);
    }
}
