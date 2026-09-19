<?php

namespace Tests\Feature\UserRequests;

use App\Enums\UserRequestStatus;
use App\Livewire\UserRequests\UserRequestDropdown;
use App\Models\Activity;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserRequestDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_dropdown_is_hidden_when_user_has_never_had_requests(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(UserRequestDropdown::class)
            ->assertDontSeeHtml('data-ui="nav-requests"');
    }

    public function test_dropdown_shows_past_requests_without_pending_badge(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'name' => 'Past Invite Night',
        ]);

        UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
            'status' => UserRequestStatus::Accepted,
        ]);

        Livewire::actingAs($recipient)
            ->test(UserRequestDropdown::class)
            ->assertSeeHtml('data-ui="nav-requests"')
            ->assertDontSeeHtml('data-ui="nav-requests-badge"')
            ->assertSee($activity->name, false)
            ->assertSee(__('ui.requests.view_all'), false)
            ->assertSee(route('requests.index'), false);
    }

    public function test_dropdown_lists_latest_requests_and_opens_inbox(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'name' => 'Chess Club',
        ]);

        UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $other->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);

        $visible = UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);

        Livewire::actingAs($recipient)
            ->test(UserRequestDropdown::class)
            ->assertSeeHtml('data-ui="nav-requests-badge"')
            ->assertSee('Chess Club', false)
            ->assertDontSee($other->displayName(), false)
            ->call('openRequest', $visible->id)
            ->assertRedirect(route('requests.index', ['request' => $visible->id]));
    }

    public function test_dropdown_only_shows_the_latest_preview_limit(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        $oldest = UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
            'created_at' => now()->subDays(10),
        ]);

        foreach (range(1, UserRequestDropdown::PREVIEW_LIMIT) as $offset) {
            $laterActivity = Activity::factory()->create([
                'created_by' => $host->id,
                'updated_by' => $host->id,
            ]);

            UserRequest::factory()->activityInvite()->create([
                'requester_id' => $host->id,
                'recipient_id' => $recipient->id,
                'subject_type' => 'activity',
                'subject_id' => $laterActivity->id,
                'created_at' => now()->subDays($offset),
            ]);
        }

        Livewire::actingAs($recipient)
            ->test(UserRequestDropdown::class)
            ->assertViewHas('requests', function ($requests) use ($oldest): bool {
                return $requests->count() === UserRequestDropdown::PREVIEW_LIMIT
                    && $requests->doesntContain('id', $oldest->id);
            });
    }

    public function test_open_request_rejects_requests_the_user_is_not_involved_in(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $stranger = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
        ]);

        $request = UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($stranger)
            ->test(UserRequestDropdown::class)
            ->call('openRequest', $request->id);
    }
}
