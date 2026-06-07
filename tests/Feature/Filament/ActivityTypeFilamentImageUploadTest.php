<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\ActivityTypes\Pages\EditActivityType;
use App\Models\ActivityType;
use App\Models\User;
use App\Support\Ui\EventListingImageResolver;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsResponsiveMedia;
use Tests\TestCase;

final class ActivityTypeFilamentImageUploadTest extends TestCase
{
    use AssertsResponsiveMedia;
    use RefreshDatabase;

    #[Test]
    public function admin_can_upload_activity_type_and_event_listing_images(): void
    {
        config(['media.test_profile' => 'full']);

        Storage::fake('public');

        $this->seed(ActivityTypeSeeder::class);

        $admin = User::factory()->admin()->create();
        $rpg = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        $this->assertNotNull($rpg);

        Livewire::actingAs($admin)
            ->test(EditActivityType::class, ['record' => $rpg->id])
            ->fillForm([
                'images' => [
                    UploadedFile::fake()->image('activity-type.jpg', 800, 600),
                ],
                'event_listing' => [
                    UploadedFile::fake()->image('event-listing.jpg', 1280, 720),
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $rpg->refresh();

        $activityMedia = $rpg->getFirstMedia('images');
        $eventMedia = $rpg->getFirstMedia(EventListingImageResolver::EVENT_LISTING_COLLECTION);

        $this->assertNotNull($activityMedia);
        $this->assertNotNull($eventMedia);
        $this->assertMediaHasResponsiveConversions($activityMedia);
        $this->assertMediaHasResponsiveConversions($eventMedia);
    }
}
