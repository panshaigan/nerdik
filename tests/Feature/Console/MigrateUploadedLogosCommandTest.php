<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\ActivityLogoSource;
use App\Enums\EventLogoSource;
use App\Models\Activity;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MigrateUploadedLogosCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_migrates_legacy_activity_and_event_logo_path_files_to_media_library(): void
    {
        Storage::fake('public');

        $activityPath = 'activity-logos/legacy-activity.webp';
        Storage::disk('public')->put($activityPath, UploadedFile::fake()->image('legacy.jpg')->getContent());
        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Upload,
            'logo_path' => $activityPath,
        ]);

        $eventPath = 'event-logos/legacy-event.webp';
        Storage::disk('public')->put($eventPath, UploadedFile::fake()->image('legacy.jpg')->getContent());
        $event = Event::factory()->create([
            'logo_source' => EventLogoSource::Upload,
            'logo_path' => $eventPath,
        ]);

        Artisan::call('media:migrate-uploaded-logos');

        $activity->refresh();
        $event->refresh();

        $this->assertNull($activity->logo_path);
        $this->assertNotNull($activity->getFirstMedia('logo'));
        Storage::disk('public')->assertMissing($activityPath);

        $this->assertNull($event->logo_path);
        $this->assertNotNull($event->getFirstMedia('logo'));
        Storage::disk('public')->assertMissing($eventPath);
    }

    #[Test]
    public function dry_run_does_not_modify_records(): void
    {
        Storage::fake('public');

        $path = 'activity-logos/dry-run.webp';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('legacy.jpg')->getContent());
        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Upload,
            'logo_path' => $path,
        ]);

        Artisan::call('media:migrate-uploaded-logos', ['--dry-run' => true]);

        $activity->refresh();

        $this->assertSame($path, $activity->logo_path);
        $this->assertNull($activity->getFirstMedia('logo'));
        Storage::disk('public')->assertExists($path);
    }
}
