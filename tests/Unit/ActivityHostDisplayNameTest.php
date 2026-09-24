<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityHostDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function host_display_name_prefers_organization_over_creator(): void
    {
        $user = User::factory()->create(['nickname' => 'HostNick']);
        $organization = Organization::factory()->create(['name' => 'Nerd Guild']);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'organization_id' => $organization->id,
        ]);
        $activity->setRelation('creator', $user);
        $activity->setRelation('organization', $organization);

        $this->assertSame('Nerd Guild', $activity->hostDisplayName());
    }

    #[Test]
    public function host_display_name_falls_back_to_creator_nickname(): void
    {
        $user = User::factory()->create(['nickname' => 'HostNick']);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'organization_id' => null,
        ]);
        $activity->setRelation('creator', $user);
        $activity->setRelation('organization', null);

        $this->assertSame('HostNick', $activity->hostDisplayName());
    }

    #[Test]
    public function host_display_name_is_empty_without_organization_or_creator(): void
    {
        $activity = new Activity;

        $this->assertSame('', $activity->hostDisplayName());
    }
}
