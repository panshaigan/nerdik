<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Livewire\Activities\ManageActivityForm;
use App\Livewire\Groups\GroupAvailabilityCalendar;
use App\Models\Activity;
use App\Models\Tag;
use App\Models\User;
use App\Services\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class GroupCalendarCreateActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_activity_form_prefills_from_group_calendar_query_params(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        Livewire::actingAs($user)
            ->withQueryParams([
                'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
                'self_hosted_starts_at' => '2026-06-15T18:00',
                'tag_ids' => [(int) $tag->id],
            ])
            ->test(ManageActivityForm::class)
            ->assertSet('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->assertSet('self_hosted_starts_at', '2026-06-15T18:00')
            ->assertSet('tag_ids', [(int) $tag->id]);
    }

    public function test_day_modal_links_to_create_activity_with_prefill_for_future_days(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Calendar Hosts');
        $tag = Tag::factory()->create();
        $group->tags()->attach($tag->id);

        $futureDate = now()->addDays(5);
        $futureDateKey = $futureDate->format('Y-m-d');

        Livewire::actingAs($owner)
            ->test(GroupAvailabilityCalendar::class, [
                'groupId' => $group->id,
                'month' => $futureDate->format('Y-m'),
            ])
            ->call('selectDate', $futureDateKey)
            ->assertSet('dayDetailOpen', true)
            ->assertSeeHtml('data-ui="group-day-create-activity"')
            ->assertSee('self_hosted_starts_at='.rawurlencode($futureDateKey.'T18:00'), false)
            ->assertSee('tag_ids', false);
    }

    public function test_day_modal_omits_create_activity_for_past_days(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Past Day Group');

        $pastDate = now()->subDays(5);
        $pastDateKey = $pastDate->format('Y-m-d');

        Livewire::actingAs($owner)
            ->test(GroupAvailabilityCalendar::class, [
                'groupId' => $group->id,
                'month' => $pastDate->format('Y-m'),
            ])
            ->call('selectDate', $pastDateKey)
            ->assertSet('dayDetailOpen', true)
            ->assertDontSeeHtml('data-ui="group-day-create-activity"');
    }
}
