<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\EventLogoSource;
use App\Livewire\Events\ManageEventForm;
use App\Models\Event;
use App\Models\User;
use App\Support\Events\EventDefaultImageCatalog;
use Database\Seeders\ActivityTypeSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManageEventFormImageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function image_tab_lists_default_event_images_when_seeded(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $this->seed(TagSeeder::class);
        $user = User::factory()->organizer()->create();
        $mediaId = app(EventDefaultImageCatalog::class)->availableMediaIds()[0] ?? null;
        $this->assertNotNull($mediaId);

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('tab', 'image')
            ->set('logo_source', EventLogoSource::Default->value)
            ->assertSeeHtml('role="radiogroup"');
    }

    #[Test]
    public function save_persists_default_listing_media_selection(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $this->seed(TagSeeder::class);
        $user = User::factory()->organizer()->create();
        $mediaId = (int) app(EventDefaultImageCatalog::class)->availableMediaIds()[0];

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Default Image Event')
            ->set('description', 'desc')
            ->set('starts_at', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', now()->addDays(1)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('logo_source', EventLogoSource::Default->value)
            ->set('listing_media_id', $mediaId)
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::query()->where('name', 'Default Image Event')->first();
        $this->assertNotNull($event);
        $this->assertSame(EventLogoSource::Default, $event->logo_source);
        $this->assertSame($mediaId, (int) $event->listing_media_id);
        $this->assertNull($event->logo_path);
    }

    #[Test]
    public function save_persists_uploaded_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->organizer()->create();
        $file = UploadedFile::fake()->image('cover.jpg', 1600, 900);

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Upload Logo Event')
            ->set('description', 'desc')
            ->set('starts_at', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', now()->addDays(1)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('logo_source', EventLogoSource::Upload->value)
            ->set('croppedLogo', $file)
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::query()->where('name', 'Upload Logo Event')->first();
        $this->assertNotNull($event);
        $this->assertSame(EventLogoSource::Upload, $event->logo_source);
        $this->assertSame('event-logos/'.$event->id.'.webp', $event->logo_path);
        Storage::disk('public')->assertExists('event-logos/'.$event->id.'.webp');
    }

    #[Test]
    public function validation_requires_crop_when_upload_selected_on_create(): void
    {
        $user = User::factory()->organizer()->create();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Missing Logo Event')
            ->set('description', 'desc')
            ->set('starts_at', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', now()->addDays(1)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('logo_source', EventLogoSource::Upload->value)
            ->call('save')
            ->assertHasErrors(['croppedLogo']);
    }

    #[Test]
    public function edit_keeps_existing_logo_without_new_crop(): void
    {
        Storage::fake('public');
        $user = User::factory()->organizer()->create();
        $path = 'event-logos/existing.webp';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('existing.jpg')->getContent());
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => EventLogoSource::Upload,
            'logo_path' => $path,
            'listing_media_id' => null,
        ]);

        Livewire::actingAs($user)
            ->test(ManageEventForm::class, ['event' => $event])
            ->set('name', 'Updated Event Name')
            ->set('logo_source', EventLogoSource::Upload->value)
            ->call('save')
            ->assertHasNoErrors();

        $event->refresh();
        $this->assertSame($path, $event->logo_path);
        $this->assertSame('Updated Event Name', $event->name);
    }

    #[Test]
    public function switching_logo_source_from_upload_to_default_deletes_stored_file(): void
    {
        Storage::fake('public');
        $this->seed(ActivityTypeSeeder::class);
        $this->seed(TagSeeder::class);
        $user = User::factory()->organizer()->create();
        $mediaId = (int) app(EventDefaultImageCatalog::class)->availableMediaIds()[0];
        $path = 'event-logos/to-delete.webp';
        Storage::disk('public')->put($path, UploadedFile::fake()->image('logo.jpg')->getContent());
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => EventLogoSource::Upload,
            'logo_path' => $path,
        ]);

        Livewire::actingAs($user)
            ->test(ManageEventForm::class, ['event' => $event])
            ->set('logo_source', EventLogoSource::Default->value)
            ->set('listing_media_id', $mediaId)
            ->call('save')
            ->assertHasNoErrors();

        $event->refresh();
        $this->assertSame(EventLogoSource::Default, $event->logo_source);
        $this->assertNull($event->logo_path);
        $this->assertSame($mediaId, (int) $event->listing_media_id);
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function edit_form_loads_existing_default_image_selection(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $this->seed(TagSeeder::class);
        $user = User::factory()->organizer()->create();
        $mediaId = (int) app(EventDefaultImageCatalog::class)->availableMediaIds()[0];
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => EventLogoSource::Default,
            'listing_media_id' => $mediaId,
        ]);

        Livewire::actingAs($user)
            ->test(ManageEventForm::class, ['event' => $event])
            ->assertSet('logo_source', EventLogoSource::Default->value)
            ->assertSet('listing_media_id', $mediaId);
    }
}
