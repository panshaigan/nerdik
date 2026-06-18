<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Livewire\Organizations\OrganizationIndex;
use App\Livewire\UserRequests\InviteUserRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserRequestInviteOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_invite_action_on_organization_row(): void
    {
        $owner = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(OrganizationIndex::class)
            ->assertSeeHtml('wire:name="user-requests.invite-user-request"')
            ->assertSeeHtml('data-ui="organization-invite-user"');
    }

    public function test_search_excludes_existing_organization_member(): void
    {
        $owner = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $member = User::factory()->create([
            'nickname' => 'orgmember42',
            'organization_id' => $organization->id,
        ]);

        Livewire::actingAs($owner)
            ->test(InviteUserRequest::class, [
                'type' => 'organization_invite',
                'subjectId' => $organization->id,
            ])
            ->call('search', 'orgmember')
            ->assertSet('lastSearchTerm', 'orgmember')
            ->assertSet('userOptions', fn (array $options): bool => ! collect($options)->contains(
                fn (array $option): bool => $option['id'] === $member->id,
            ));
    }

    public function test_send_creates_pending_organization_invite(): void
    {
        $owner = User::factory()->create(['is_event_organizer' => true]);
        $invitee = User::factory()->create(['organization_id' => null]);
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(InviteUserRequest::class, [
                'type' => 'organization_invite',
                'subjectId' => $organization->id,
            ])
            ->set('selectedUserId', $invitee->id)
            ->call('send')
            ->assertDispatched('user-request-sent');

        $this->assertDatabaseHas('user_requests', [
            'type' => UserRequestType::OrganizationInvite->value,
            'status' => UserRequestStatus::Pending->value,
            'requester_id' => $owner->id,
            'recipient_id' => $invitee->id,
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
        ]);
    }
}
