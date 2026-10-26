<?php

namespace Tests\Feature\Profile;

use App\Livewire\Activities\OrganizationBadgeContact;
use App\Livewire\Activities\OrganizationContactPopover;
use App\Livewire\Activities\UserBadgeContact;
use App\Livewire\Activities\UserContactPopover;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Support\Browse\BrowseSearchUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileContactPopoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_badge_contact_renders_modal_trigger_for_authenticated_viewer(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($viewer);

        $html = Blade::render(
            '<x-user-badge :user="$user" />',
            ['user' => $target],
        );

        $this->assertStringContainsString('data-ui="user-badge-contact-trigger"', $html);
        $this->assertStringContainsString('wire:click.stop="openModal"', $html);
    }

    public function test_user_badge_contact_close_modal_resets_state(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        Livewire::actingAs($viewer)
            ->test(UserBadgeContact::class, [
                'user' => $target,
            ])
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->call('closeModal')
            ->assertSet('modalOpen', false);
    }

    public function test_user_badge_contact_does_not_render_modal_trigger_for_guest(): void
    {
        $target = User::factory()->create();

        $html = Blade::render(
            '<x-user-badge :user="$user" />',
            ['user' => $target],
        );

        $this->assertStringNotContainsString('data-ui="user-badge-contact"', $html);
        $this->assertStringNotContainsString('wire:click.stop="openModal"', $html);
    }

    public function test_user_badge_contact_modal_opens_and_loads_popover_content(): void
    {
        $host = User::factory()->create(['email' => 'host@example.test']);
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'show_contact_email' => true,
        ]);

        Livewire::actingAs($host)
            ->test(UserBadgeContact::class, [
                'user' => $participant,
            ])
            ->assertSet('modalOpen', false)
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->assertSee('mailto:participant@example.test');
    }

    public function test_user_badge_contact_modal_opens_with_container_class_from_attributes(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $activity = Activity::factory()->create(['created_by' => $host->id]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'show_contact_email' => true,
        ]);

        Livewire::actingAs($host)
            ->test(UserBadgeContact::class, [
                'user' => $participant,
                'containerClass' => 'inline-flex min-w-0 min-w-0 flex-1',
            ])
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->assertSee('mailto:participant@example.test');
    }

    public function test_organization_badge_contact_renders_modal_trigger_for_authenticated_viewer(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        $organization = Organization::factory()->create([
            'name' => 'Test Guild',
        ]);

        $this->actingAs($viewer);

        $html = Blade::render(
            '<x-user-badge :user="$user" :organization="$organization" />',
            [
                'user' => $host,
                'organization' => $organization,
            ],
        );

        $this->assertStringContainsString('data-ui="organization-badge-contact-trigger"', $html);
        $this->assertStringContainsString('wire:click.stop="openModal"', $html);
        $this->assertStringNotContainsString('data-ui="user-badge-contact-trigger"', $html);
    }

    public function test_organization_badge_contact_does_not_render_modal_trigger_for_guest(): void
    {
        $host = User::factory()->create();
        $organization = Organization::factory()->create();

        $html = Blade::render(
            '<x-user-badge :user="$user" :organization="$organization" />',
            [
                'user' => $host,
                'organization' => $organization,
            ],
        );

        $this->assertStringNotContainsString('data-ui="organization-badge-contact"', $html);
        $this->assertStringNotContainsString('wire:click.stop="openModal"', $html);
    }

    public function test_organization_badge_contact_close_modal_resets_state(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        $organization = Organization::factory()->create();

        Livewire::actingAs($viewer)
            ->test(OrganizationBadgeContact::class, [
                'organization' => $organization,
                'user' => $host,
            ])
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->call('closeModal')
            ->assertSet('modalOpen', false);
    }

    public function test_organization_badge_contact_modal_opens_and_loads_popover_content(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        $organization = Organization::factory()->create([
            'name' => 'Guild of Nerds',
            'description' => '<p>We run tabletop events.</p>',
        ]);
        User::factory()->create([
            'nickname' => 'guild_member',
            'organization_id' => $organization->id,
        ]);
        $rpgType = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $boardType = ActivityType::factory()->create(['slug' => ActivityType::SLUG_BOARD]);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        $upcomingRpg = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $rpgType->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        $upcomingBoard = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $boardType->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        $pastRpg = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $rpgType->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $upcomingRpg->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(4),
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $upcomingBoard->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(4),
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $pastRpg->id,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(4),
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(OrganizationBadgeContact::class, [
                'organization' => $organization,
                'user' => $host,
            ])
            ->assertSet('modalOpen', false)
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->assertSee(__('ui.common.organization'), false)
            ->assertSee('Guild of Nerds', false)
            ->assertSee('We run tabletop events.')
            ->assertSee(__('ui.organizations.scheduled_section'))
            ->assertSee(__('ui.activities.types.rpg'))
            ->assertSee(__('ui.activities.types.board'))
            ->assertSee(__('ui.organizations.past_section'))
            ->assertSee('guild_member')
            ->assertSee(__('ui.organizations.events'))
            ->assertSeeHtml(BrowseSearchUrl::forOrganization($organization));
    }

    public function test_organization_contact_popover_lists_members_with_organization_id(): void
    {
        $viewer = User::factory()->create();
        $organization = Organization::factory()->create();
        $firstMember = User::factory()->create([
            'nickname' => 'alpha_member',
            'organization_id' => $organization->id,
        ]);
        $secondMember = User::factory()->create([
            'nickname' => 'beta_member',
            'organization_id' => $organization->id,
        ]);
        User::factory()->create([
            'nickname' => 'outsider',
            'organization_id' => null,
        ]);
        User::factory()->create([
            'nickname' => 'former_member',
            'organization_id' => $organization->id,
            'is_deleted' => true,
        ]);

        Livewire::actingAs($viewer)
            ->test(OrganizationContactPopover::class, [
                'targetOrganizationId' => $organization->id,
            ])
            ->assertSee('alpha_member')
            ->assertSee('beta_member')
            ->assertDontSee('outsider')
            ->assertDontSee('former_member')
            ->assertSee(__('ui.organizations.members_section'));
    }

    public function test_organization_contact_popover_shows_empty_members_message(): void
    {
        $viewer = User::factory()->create();
        $organization = Organization::factory()->create();

        Livewire::actingAs($viewer)
            ->test(OrganizationContactPopover::class, [
                'targetOrganizationId' => $organization->id,
            ])
            ->assertSee(__('ui.organizations.no_members'));
    }

    public function test_organization_badge_contact_popover_resolves_stats_by_type(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        $organization = Organization::factory()->create();
        $rpgType = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $boardType = ActivityType::factory()->create(['slug' => ActivityType::SLUG_BOARD]);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        $upcoming = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $rpgType->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        $past = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $boardType->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $upcoming->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $past->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(3),
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        Livewire::actingAs($viewer)
            ->test(OrganizationContactPopover::class, [
                'targetOrganizationId' => $organization->id,
            ])
            ->assertSee(__('ui.organizations.scheduled_section'))
            ->assertSee(__('ui.activities.types.rpg'))
            ->assertSee(__('ui.organizations.past_section'))
            ->assertSee(__('ui.activities.types.board'))
            ->assertSee(__('ui.organizations.events'))
            ->assertSeeHtml(BrowseSearchUrl::forOrganization($organization));
    }

    public function test_organization_contact_popover_lists_event_series_and_places(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        $organization = Organization::factory()->create();
        $series = EventSeries::factory()->create([
            'name' => 'Monthly Guild Night',
            'created_by' => $host->id,
        ]);
        $venue = Place::factory()->create([
            'name' => 'Guild Hall Venue',
            'type' => Place::TYPE_VENUE,
        ]);
        $event = Event::factory()->create([
            'organization_id' => $organization->id,
            'event_series_id' => $series->id,
            'is_public' => true,
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);
        $event->places()->attach($venue->id);

        Livewire::actingAs($viewer)
            ->test(OrganizationContactPopover::class, [
                'targetOrganizationId' => $organization->id,
            ])
            ->assertSee('Monthly Guild Night')
            ->assertSee('Guild Hall Venue')
            ->assertSeeHtml(route('event-series.show', $series))
            ->assertSeeHtml(BrowseSearchUrl::forPlace($venue));
    }

    public function test_organization_contact_popover_shows_empty_series_and_places_messages(): void
    {
        $viewer = User::factory()->create();
        $organization = Organization::factory()->create();

        Livewire::actingAs($viewer)
            ->test(OrganizationContactPopover::class, [
                'targetOrganizationId' => $organization->id,
            ])
            ->assertSee(__('ui.organizations.no_event_series'))
            ->assertSee(__('ui.organizations.no_places'));
    }

    public function test_organization_contact_popover_loads_content_for_guest(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Guest Visible Guild',
            'description' => '<p>Public org preview for guests.</p>',
        ]);

        Livewire::test(OrganizationContactPopover::class, [
            'targetOrganizationId' => $organization->id,
        ])
            ->assertSee('Guest Visible Guild', false)
            ->assertSee('Public org preview for guests.')
            ->assertSee(__('ui.organizations.events'))
            ->assertSeeHtml(BrowseSearchUrl::forOrganization($organization))
            ->assertDontSeeHtml('data-ui="organization-contact-popover-requests"');
    }

    public function test_organization_badge_with_contact_popover_disabled_does_not_render_trigger(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        $organization = Organization::factory()->create();

        $this->actingAs($viewer);

        $html = Blade::render(
            '<x-user-badge :user="$user" :organization="$organization" :contact-popover="false" />',
            [
                'user' => $host,
                'organization' => $organization,
            ],
        );

        $this->assertStringNotContainsString('data-ui="organization-badge-contact"', $html);
        $this->assertStringNotContainsString('wire:click.stop="openModal"', $html);
    }

    public function test_user_contact_popover_shows_contact_methods_for_host_and_participant(): void
    {
        $host = User::factory()->create(['email' => 'host@example.test']);
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'facebook_id' => '12345',
            'discord_id' => '67890',
            'google_email' => 'participant-google@example.test',
            'show_contact_email' => true,
            'show_contact_facebook' => true,
            'show_contact_google' => true,
            'show_contact_discord' => true,
        ]);

        $html = Livewire::actingAs($host)
            ->test(UserContactPopover::class, [
                'targetUserId' => $participant->id,
            ])
            ->html();

        $this->assertStringContainsString('mailto:participant@example.test', $html);
        $this->assertStringNotContainsString('https://www.facebook.com/profile.php?id=12345', $html);
        $this->assertStringNotContainsString('https://www.facebook.com/messages/t/12345', $html);
        $this->assertStringNotContainsString('https://m.me/12345', $html);
        $this->assertStringContainsString('https://discord.com/users/67890', $html);
        $this->assertStringContainsString('discord://-/users/67890', $html);
        $this->assertStringContainsString(__('ui.profile.contact_section_email'), $html);
        $this->assertStringNotContainsString(__('ui.profile.contact_section_facebook'), $html);
        $this->assertStringContainsString(__('ui.profile.contact_section_discord'), $html);
        $this->assertStringNotContainsString('participant-google@example.test', $html);
        $this->assertStringContainsString(__('ui.profile.contact_participation_section'), $html);
        $this->assertStringContainsString(__('ui.activities.types.'.ActivityType::SLUG_RPG), $html);
        $this->assertStringContainsString('window.copyToClipboard', $html);
        $this->assertStringNotContainsString('navigator.clipboard?.writeText', $html);
    }

    public function test_user_contact_popover_uses_manual_facebook_profile_url_without_oauth_link(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'facebook_profile_url' => 'https://www.facebook.com/manual-only',
            'show_contact_facebook' => true,
        ]);

        $html = Livewire::actingAs($host)
            ->test(UserContactPopover::class, [
                'targetUserId' => $participant->id,
            ])
            ->html();

        $this->assertStringContainsString('https://www.facebook.com/manual-only', $html);
        $this->assertStringContainsString(__('ui.profile.contact_facebook_profile'), $html);
    }

    public function test_user_contact_popover_uses_stored_facebook_provider_data_urls(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'facebook_id' => 'app-scoped-id',
            'facebook_data' => [
                'profile_url' => 'https://www.facebook.com/zuck',
                'messages_url' => 'https://www.facebook.com/messages/t/zuck',
                'messenger_url' => 'https://m.me/zuck',
            ],
            'show_contact_facebook' => true,
        ]);

        $html = Livewire::actingAs($host)
            ->test(UserContactPopover::class, [
                'targetUserId' => $participant->id,
            ])
            ->html();

        $this->assertStringNotContainsString(__('ui.profile.contact_section_facebook'), $html);
        $this->assertStringNotContainsString('https://www.facebook.com/zuck', $html);
        $this->assertStringNotContainsString('https://www.facebook.com/messages/t/zuck', $html);
        $this->assertStringNotContainsString('https://m.me/zuck', $html);
        $this->assertStringNotContainsString('https://www.facebook.com/profile.php?id=app-scoped-id', $html);
    }

    public function test_user_contact_popover_shows_hosted_stats_by_type(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        $rpgType = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $boardType = ActivityType::factory()->create(['slug' => ActivityType::SLUG_BOARD]);

        Activity::factory()->count(2)->create([
            'created_by' => $host->id,
            'activity_type_id' => $rpgType->id,
        ]);
        Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $boardType->id,
        ]);

        $html = Livewire::actingAs($viewer)
            ->test(UserContactPopover::class, [
                'targetUserId' => $host->id,
            ])
            ->html();

        $this->assertStringContainsString(__('ui.profile.contact_hosted_section'), $html);
        $this->assertStringContainsString(__('ui.activities.types.'.ActivityType::SLUG_RPG), $html);
        $this->assertStringContainsString(__('ui.activities.types.'.ActivityType::SLUG_BOARD), $html);
    }

    public function test_user_contact_popover_shows_hero_with_organization(): void
    {
        $viewer = User::factory()->create();
        $organization = Organization::factory()->create([
            'name' => 'Castle Games Night',
            'acronym' => 'CGN',
        ]);
        $target = User::factory()->create([
            'nickname' => 'pixel_mage',
            'organization_id' => $organization->id,
        ]);

        $html = Livewire::actingAs($viewer)
            ->test(UserContactPopover::class, [
                'targetUserId' => $target->id,
            ])
            ->html();

        $this->assertStringContainsString('pixel_mage', $html);
        $this->assertStringContainsString('CGN', $html);
        $this->assertStringNotContainsString('pixel_mage [CGN]', $html);
    }

    public function test_user_contact_popover_uses_google_email_when_main_email_hidden(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'google_email' => 'participant-google@example.test',
            'show_contact_email' => false,
            'show_contact_google' => true,
        ]);

        $html = Livewire::actingAs($host)
            ->test(UserContactPopover::class, [
                'targetUserId' => $participant->id,
            ])
            ->html();

        $this->assertStringContainsString('mailto:participant-google@example.test', $html);
        $this->assertStringNotContainsString('mailto:participant@example.test', $html);
    }

    public function test_user_contact_popover_hides_google_email_when_main_email_is_shown(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'google_email' => 'participant-google@example.test',
            'show_contact_email' => true,
            'show_contact_google' => true,
        ]);

        $html = Livewire::actingAs($host)
            ->test(UserContactPopover::class, [
                'targetUserId' => $participant->id,
            ])
            ->html();

        $this->assertStringContainsString('mailto:participant@example.test', $html);
        $this->assertStringNotContainsString('mailto:participant-google@example.test', $html);
        $this->assertStringNotContainsString('participant-google@example.test', $html);
    }

    public function test_user_contact_popover_hides_contact_methods_for_unauthorized_viewer(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $stranger = User::factory()->create();
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'show_contact_email' => true,
            'show_contact_facebook' => true,
            'show_contact_google' => true,
            'show_contact_discord' => true,
        ]);

        $html = Livewire::actingAs($stranger)
            ->test(UserContactPopover::class, [
                'targetUserId' => $participant->id,
            ])
            ->html();

        $this->assertStringNotContainsString(__('ui.profile.contact_not_allowed'), $html);
        $this->assertStringNotContainsString('mailto:participant@example.test', $html);
        $this->assertStringNotContainsString(__('ui.profile.contact_methods_title'), $html);
    }
}
