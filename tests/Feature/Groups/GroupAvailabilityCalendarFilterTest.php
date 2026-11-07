<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Livewire\Groups\GroupAvailabilityCalendar;
use App\Models\User;
use App\Models\UserAvailabilityWeekday;
use App\Services\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class GroupAvailabilityCalendarFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_min_available_filter_marks_matching_days(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Filter Party');
        $group->users()->attach($member->id);

        UserAvailabilityWeekday::query()->create([
            'user_id' => $owner->id,
            'sat' => true,
        ]);
        UserAvailabilityWeekday::query()->create([
            'user_id' => $member->id,
            'sat' => true,
        ]);

        Livewire::actingAs($owner)
            ->test(GroupAvailabilityCalendar::class, [
                'groupId' => $group->id,
                'month' => '2026-03',
            ])
            ->set('minAvailable', 2)
            ->assertSet('minAvailable', 2)
            ->assertSeeHtml('data-matches-filter="1"')
            ->assertSeeHtml('data-matches-filter="0"');
    }
}
