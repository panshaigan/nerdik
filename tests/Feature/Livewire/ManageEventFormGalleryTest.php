<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Actions\Media\StoreUserGalleryImage;
use App\Enums\EventLogoSource;
use App\Livewire\Events\ManageEventForm;
use App\Models\Event;
use App\Models\User;
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
            UploadedFile::fake()->image('gallery.jpg', 1600, 900),
            1280,
            720,
        );

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Gallery Image Event')
            ->set('description', 'desc')
            ->set('starts_at', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', now()->addDays(1)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
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
        $file = UploadedFile::fake()->image('cover.jpg', 1600, 900);

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Upload To Gallery Event')
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
            UploadedFile::fake()->image('gallery.jpg', 1600, 900),
            1280,
            720,
        );

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('name', 'Foreign Gallery Event')
            ->set('description', 'desc')
            ->set('starts_at', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('enrollment_windows.0.starts_at', now()->addDays(1)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('logo_source', EventLogoSource::Gallery->value)
            ->set('gallery_media_id', (int) $media->id)
            ->call('save')
            ->assertHasErrors(['gallery_media_id']);
    }
}
