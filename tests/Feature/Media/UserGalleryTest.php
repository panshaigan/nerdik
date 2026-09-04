<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Actions\Media\DeleteUserGalleryImage;
use App\Actions\Media\StoreUserGalleryImage;
use App\Enums\AvatarSource;
use App\Enums\EventLogoSource;
use App\Models\Event;
use App\Models\User;
use App\Support\Media\UserGalleryCatalog;
use App\Support\Ui\EventListingImageResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserGalleryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_store_and_list_gallery_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('cover.jpg', 1600, 900);

        $media = app(StoreUserGalleryImage::class)($user, $file, 1280, 720);

        $this->assertSame(UserGalleryCatalog::COLLECTION, $media->collection_name);
        $this->assertSame($user->id, (int) $media->model_id);

        $catalog = app(UserGalleryCatalog::class)->forUser($user->fresh());
        $this->assertCount(1, $catalog);
        $this->assertSame((int) $media->id, $catalog[0]['media_id']);
        $this->assertTrue(app(UserGalleryCatalog::class)->mediaBelongsToUser((int) $media->id, $user));
    }

    #[Test]
    public function user_cannot_delete_another_users_gallery_image(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = app(StoreUserGalleryImage::class)(
            $owner,
            UploadedFile::fake()->image('cover.jpg', 1600, 900),
            1280,
            720,
        );

        $this->expectException(AuthorizationException::class);
        app(DeleteUserGalleryImage::class)($other, (int) $media->id);
    }

    #[Test]
    public function deleting_gallery_image_nulls_event_and_avatar_references(): void
    {
        Storage::fake('public');
        $user = User::factory()->organizer()->create();
        $media = app(StoreUserGalleryImage::class)(
            $user,
            UploadedFile::fake()->image('cover.jpg', 1600, 900),
            1280,
            720,
        );

        $event = Event::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => EventLogoSource::Gallery,
            'gallery_media_id' => $media->id,
            'listing_media_id' => null,
            'logo_path' => null,
        ]);

        $profile = $user->profile()->firstOrCreate();
        $profile->forceFill([
            'avatar_source' => AvatarSource::Gallery,
            'gallery_media_id' => $media->id,
        ])->save();

        app(DeleteUserGalleryImage::class)($user, (int) $media->id);

        $event->refresh();
        $profile->refresh();

        $this->assertNull($event->gallery_media_id);
        $this->assertNull($profile->gallery_media_id);
        $this->assertFalse(
            app(EventListingImageResolver::class)->resolve($event->fresh())->hasDisplayableImage()
        );
    }

    #[Test]
    public function profile_gallery_component_can_upload_and_delete(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.manage-gallery-form')
            ->set('croppedLogo', UploadedFile::fake()->image('gallery.jpg', 1600, 900))
            ->call('uploadImage')
            ->assertHasNoErrors();

        $this->assertCount(1, $user->fresh()->getMedia(UserGalleryCatalog::COLLECTION));
        $mediaId = (int) $user->fresh()->getMedia(UserGalleryCatalog::COLLECTION)->first()->id;

        Volt::test('profile.manage-gallery-form')
            ->call('deleteImage', $mediaId)
            ->assertHasNoErrors();

        $this->assertCount(0, $user->fresh()->getMedia(UserGalleryCatalog::COLLECTION));
    }
}
