<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Actions\Media\StoreUserGalleryImage;
use App\Enums\EventLogoSource;
use App\Livewire\Events\ManageEventForm;
use App\Models\Event;
use App\Models\User;
use App\Support\Media\MediaPictureSources;
use App\Support\Media\UserGalleryCatalog;
use App\Support\Ui\EventListingImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManageEventFormGalleryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function save_persists_gallery_media_selection(): void
    {
        Storage::fake('public');
        $user = User::factory()->organizer()->create();
        $media = app(StoreUserGalleryImage::class)(
            $user,
            UploadedFile::fake()->image('gallery.jpg', 800, 450),
            1280,
            720,
        );

        $schedule = $this->eventFormSchedule();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Gallery Image Event')
            ->set('description', 'desc')
            ->set('starts_at', $schedule['starts_at'])
            ->set('ends_at', $schedule['ends_at'])
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', $schedule['window_starts_at'])
            ->set('enrollment_windows.0.ends_at', $schedule['ends_at'])
            ->set('logo_source', EventLogoSource::Gallery->value)
            ->set('gallery_media_id', (int) $media->id)
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::query()->where('name', 'Gallery Image Event')->first();
        $this->assertNotNull($event);
        $this->assertSame(EventLogoSource::Gallery, $event->logo_source);
        $this->assertSame((int) $media->id, (int) $event->gallery_media_id);
        $this->assertNull($event->getFirstMedia('logo'));

        $picture = app(EventListingImageResolver::class)->resolve($event);
        $this->assertTrue($picture->hasDisplayableImage());
    }

    #[Test]
    public function upload_saves_into_user_gallery_and_sets_gallery_source(): void
    {
        Storage::fake('public');
        $user = User::factory()->organizer()->create();
        $file = UploadedFile::fake()->image('cover.jpg', 800, 450);
        $schedule = $this->eventFormSchedule();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Upload To Gallery Event')
            ->set('description', 'desc')
            ->set('starts_at', $schedule['starts_at'])
            ->set('ends_at', $schedule['ends_at'])
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', $schedule['window_starts_at'])
            ->set('enrollment_windows.0.ends_at', $schedule['ends_at'])
            ->set('logo_source', EventLogoSource::Upload->value)
            ->set('croppedLogo', $file)
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::query()->where('name', 'Upload To Gallery Event')->first();
        $this->assertNotNull($event);
        $this->assertSame(EventLogoSource::Gallery, $event->logo_source);
        $this->assertNotNull($event->gallery_media_id);
        $this->assertNull($event->getFirstMedia('logo'));
        $this->assertTrue(
            app(UserGalleryCatalog::class)->mediaBelongsToUser((int) $event->gallery_media_id, $user)
        );
    }

    #[Test]
    public function rejects_foreign_gallery_media_id(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $user = User::factory()->organizer()->create();
        $media = app(StoreUserGalleryImage::class)(
            $owner,
            UploadedFile::fake()->image('gallery.jpg', 800, 450),
            1280,
            720,
        );

        $schedule = $this->eventFormSchedule();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Foreign Gallery Event')
            ->set('description', 'desc')
            ->set('starts_at', $schedule['starts_at'])
            ->set('ends_at', $schedule['ends_at'])
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', $schedule['window_starts_at'])
            ->set('enrollment_windows.0.ends_at', $schedule['ends_at'])
            ->set('logo_source', EventLogoSource::Gallery->value)
            ->set('gallery_media_id', (int) $media->id)
            ->call('save')
            ->assertHasErrors(['gallery_media_id']);
    }

    #[Test]
    public function gallery_selection_with_crop_stores_entity_logo_without_mutating_gallery(): void
    {
        Storage::fake('public');
        $user = User::factory()->organizer()->create();
        $source = UploadedFile::fake()->image('original.jpg', 640, 360);
        $media = app(StoreUserGalleryImage::class)(
            $user,
            UploadedFile::fake()->image('gallery.jpg', 800, 450),
            1280,
            720,
            $source,
        );
        $media->refresh();
        $gallerySourcePath = $media->getCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY);
        $galleryFileName = $media->file_name;

        $crop = UploadedFile::fake()->image('crop.jpg', 800, 450);
        $schedule = $this->eventFormSchedule();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Gallery Crop Event')
            ->set('description', 'desc')
            ->set('starts_at', $schedule['starts_at'])
            ->set('ends_at', $schedule['ends_at'])
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', $schedule['window_starts_at'])
            ->set('enrollment_windows.0.ends_at', $schedule['ends_at'])
            ->set('logo_source', EventLogoSource::Gallery->value)
            ->set('gallery_media_id', (int) $media->id)
            ->set('croppedLogo', $crop)
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::query()->where('name', 'Gallery Crop Event')->first();
        $this->assertNotNull($event);
        $this->assertSame(EventLogoSource::Gallery, $event->logo_source);
        $this->assertSame((int) $media->id, (int) $event->gallery_media_id);
        $this->assertNotNull($event->getFirstMedia('logo'));

        $media->refresh();
        $this->assertSame($galleryFileName, $media->file_name);
        $this->assertSame($gallerySourcePath, $media->getCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY));

        $entityLogo = $event->getFirstMedia('logo');
        $this->assertNotNull($entityLogo);

        $picture = app(EventListingImageResolver::class)->resolve($event);
        $this->assertTrue($picture->hasDisplayableImage());
        $this->assertSame(
            MediaPictureSources::fromMediaWithPreset($entityLogo, 'listing_card')->webpSrc(),
            $picture->sources?->webpSrc(),
        );
    }

    #[Test]
    public function gallery_recrop_edit_preserves_entity_logo_when_selection_unchanged(): void
    {
        Storage::fake('public');
        $user = User::factory()->organizer()->create();
        $media = app(StoreUserGalleryImage::class)(
            $user,
            UploadedFile::fake()->image('gallery.jpg', 800, 450),
            1280,
            720,
            UploadedFile::fake()->image('original.jpg', 640, 360),
        );

        $schedule = $this->eventFormSchedule();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Preserve Crop Event')
            ->set('description', 'desc')
            ->set('starts_at', $schedule['starts_at'])
            ->set('ends_at', $schedule['ends_at'])
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', $schedule['window_starts_at'])
            ->set('enrollment_windows.0.ends_at', $schedule['ends_at'])
            ->set('logo_source', EventLogoSource::Gallery->value)
            ->set('gallery_media_id', (int) $media->id)
            ->set('croppedLogo', UploadedFile::fake()->image('crop.jpg', 800, 450))
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::query()->where('name', 'Preserve Crop Event')->first();
        $this->assertNotNull($event);
        $logoId = (int) $event->getFirstMedia('logo')?->id;

        Livewire::actingAs($user)
            ->test(ManageEventForm::class, ['event' => $event])
            ->set('logo_source', EventLogoSource::Gallery->value)
            ->set('gallery_media_id', (int) $media->id)
            ->call('save')
            ->assertHasNoErrors();

        $event->refresh();
        $this->assertNotNull($event->getFirstMedia('logo'));
        $this->assertSame($logoId, (int) $event->getFirstMedia('logo')?->id);
    }

    #[Test]
    public function gallery_crop_dropzone_uses_upload_labels_without_saved_hint(): void
    {
        Storage::fake('public');
        $user = User::factory()->organizer()->create();
        $media = app(StoreUserGalleryImage::class)(
            $user,
            UploadedFile::fake()->image('gallery.jpg', 800, 450),
            1280,
            720,
            UploadedFile::fake()->image('original.jpg', 640, 360),
        );

        $html = Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('tab', 'image')
            ->set('logo_source', EventLogoSource::Gallery->value)
            ->set('gallery_media_id', (int) $media->id)
            ->html();

        $this->assertStringContainsString('data-label-choose="'.e(__('ui.common.upload_image')).'"', $html);
        $this->assertStringContainsString(__('ui.events.image_upload'), $html);
        $this->assertStringContainsString(
            __('ui.common.cover_image_upload_help', ['max' => '5 MB']),
            $html,
        );
        $this->assertStringContainsString('data-image-crop-recrop-saved', $html);
        $this->assertStringNotContainsString('data-image-crop-recrop-saved-hint', $html);
        $this->assertStringNotContainsString(
            'Crop again to adjust the existing image, or choose a new file.',
            $html,
        );
    }

    #[Test]
    public function admin_can_save_edit_keeping_owners_gallery_media(): void
    {
        Storage::fake('public');
        $owner = User::factory()->organizer()->create();
        $admin = User::factory()->admin()->create();
        $media = app(StoreUserGalleryImage::class)(
            $owner,
            UploadedFile::fake()->image('gallery.jpg', 800, 450),
            1280,
            720,
        );

        $schedule = $this->eventFormSchedule();
        $event = Event::factory()->create([
            'name' => 'Owner Gallery Event',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'logo_source' => EventLogoSource::Gallery,
            'gallery_media_id' => $media->id,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        Livewire::actingAs($admin)
            ->test(ManageEventForm::class, ['event' => $event])
            ->set('name', 'Owner Gallery Event Updated')
            ->set('description', 'desc')
            ->set('starts_at', $schedule['starts_at'])
            ->set('ends_at', $schedule['ends_at'])
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', $schedule['window_starts_at'])
            ->set('enrollment_windows.0.ends_at', $schedule['ends_at'])
            ->call('save')
            ->assertHasNoErrors();

        $event->refresh();
        $this->assertSame('Owner Gallery Event Updated', $event->name);
        $this->assertSame(EventLogoSource::Gallery, $event->logo_source);
        $this->assertSame((int) $media->id, (int) $event->gallery_media_id);
    }

    #[Test]
    public function admin_cannot_attach_unrelated_foreign_gallery_media_on_edit(): void
    {
        Storage::fake('public');
        $owner = User::factory()->organizer()->create();
        $admin = User::factory()->admin()->create();
        $attached = app(StoreUserGalleryImage::class)(
            $owner,
            UploadedFile::fake()->image('attached.jpg', 800, 450),
            1280,
            720,
        );
        $foreign = app(StoreUserGalleryImage::class)(
            $owner,
            UploadedFile::fake()->image('foreign.jpg', 800, 450),
            1280,
            720,
        );

        $schedule = $this->eventFormSchedule();
        $event = Event::factory()->create([
            'name' => 'Owner Gallery Event',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'logo_source' => EventLogoSource::Gallery,
            'gallery_media_id' => $attached->id,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        Livewire::actingAs($admin)
            ->test(ManageEventForm::class, ['event' => $event])
            ->set('description', 'desc')
            ->set('starts_at', $schedule['starts_at'])
            ->set('ends_at', $schedule['ends_at'])
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', $schedule['window_starts_at'])
            ->set('enrollment_windows.0.ends_at', $schedule['ends_at'])
            ->set('gallery_media_id', (int) $foreign->id)
            ->call('save')
            ->assertHasErrors(['gallery_media_id']);
    }

    /**
     * @return array{starts_at: string, ends_at: string, window_starts_at: string}
     */
    private function eventFormSchedule(): array
    {
        $now = now();

        return [
            'starts_at' => $now->copy()->addDays(7)->format('Y-m-d\\TH:i'),
            'ends_at' => $now->copy()->addDays(8)->format('Y-m-d\\TH:i'),
            'window_starts_at' => $now->copy()->addDays(1)->format('Y-m-d\\TH:i'),
        ];
    }
}
