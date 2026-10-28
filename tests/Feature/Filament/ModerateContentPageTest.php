<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\ActivityLogoSource;
use App\Filament\Admin\Pages\ModerateContent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class ModerateContentPageTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    #[Test]
    public function admin_can_open_content_moderation_page(): void
    {
        $admin = User::factory()->admin()->create();
        $activity = Activity::factory()->create([
            'name' => 'Moderation target activity',
            'logo_source' => ActivityLogoSource::Upload,
        ]);
        $this->attachTagSampleMedia($activity, collection: 'logo');

        Livewire::actingAs($admin)
            ->test(ModerateContent::class)
            ->set('activeTab', 'activities')
            ->assertOk()
            ->assertCanSeeTableRecords([$activity]);
    }

    #[Test]
    public function non_admin_cannot_open_content_moderation_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(ModerateContent::getUrl())
            ->assertNotFound();
    }
}
