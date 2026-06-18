<?php

declare(strict_types=1);

namespace Tests\Feature\Avatar;

use App\Actions\Avatars\AttachUserAvatarFromPath;
use App\Actions\Avatars\ResolveAvatarUrl;
use App\Enums\AvatarSource;
use App\Events\UserAvatarReady;
use App\Listeners\NotifyUserAvatarReady;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class AvatarResponsiveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_resolve_avatar_url_returns_original_media_while_conversions_pending(): void
    {
        Queue::fake();
        config([
            'media.queue_conversions' => true,
            'media-library.queue_conversions_by_default' => true,
            'media-library.queue_connection_name' => 'database',
        ]);

        Storage::fake('public');
        $user = User::factory()->create();
        $user->profile()->update([
            'avatar_source' => AvatarSource::Uploaded,
            'avatar_initials' => 'XY',
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/pending.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        $user->addMedia($tempPath)
            ->withCustomProperties(['width' => 512, 'height' => 512])
            ->toMediaCollection('avatar');

        Queue::assertPushed(PerformConversionsJob::class);

        $user = $user->fresh();
        $url = app(ResolveAvatarUrl::class)($user);

        $this->assertSame($user->pendingAvatarOriginalUrl(), $url);
        $this->assertStringNotContainsString('ui-avatars.com', $url);
    }

    #[Test]
    public function test_avatar_conversions_pending_until_all_three_exist(): void
    {
        Queue::fake();
        config([
            'media.queue_conversions' => true,
            'media-library.queue_conversions_by_default' => true,
            'media-library.queue_connection_name' => 'database',
        ]);

        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/pending-check.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        $user->addMedia($tempPath)
            ->withCustomProperties(['width' => 512, 'height' => 512])
            ->toMediaCollection('avatar');

        $this->assertTrue($user->fresh()->avatarConversionsPending());
    }

    #[Test]
    public function test_user_badge_renders_avatar_32_img_for_ready_avatar_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/badge.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        app(AttachUserAvatarFromPath::class)($user, $tempPath);

        $html = view('components.user-badge', [
            'user' => $user->fresh(),
            'size' => 'sm',
            'avatarOnly' => true,
            'contactPopover' => false,
        ])->render();

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('avatar_32', $html);
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringNotContainsString('srcset=', $html);
    }

    #[Test]
    public function test_notify_user_avatar_ready_broadcasts_when_all_conversions_finish(): void
    {
        Event::fake([UserAvatarReady::class]);
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/notify-ready.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        app(AttachUserAvatarFromPath::class)($user, $tempPath);

        $media = $user->fresh()->getFirstMedia('avatar');
        $this->assertNotNull($media);
        $media->setRelation('model', $user->fresh());

        $conversion = Conversion::create('avatar_512');

        app(NotifyUserAvatarReady::class)->handle(new ConversionHasBeenCompletedEvent($media, $conversion));

        Event::assertDispatched(UserAvatarReady::class, fn (UserAvatarReady $event): bool => $event->userId === $user->id);
    }

    #[Test]
    public function test_notify_user_avatar_ready_waits_until_all_conversions_finish(): void
    {
        Event::fake([UserAvatarReady::class]);
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/notify-pending.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        app(AttachUserAvatarFromPath::class)($user, $tempPath);

        $media = $user->fresh()->getFirstMedia('avatar');
        $this->assertNotNull($media);
        $media->generated_conversions = [
            'avatar_32' => true,
            'avatar_118' => true,
        ];
        $media->save();
        $media->setRelation('model', $user->fresh());

        $conversion = Conversion::create('avatar_118');

        app(NotifyUserAvatarReady::class)->handle(new ConversionHasBeenCompletedEvent($media, $conversion));

        Event::assertNotDispatched(UserAvatarReady::class);
    }

    #[Test]
    public function test_notify_user_avatar_ready_ignores_non_avatar_collections(): void
    {
        Event::fake([UserAvatarReady::class]);

        $user = User::factory()->create();
        $media = new Media([
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->id,
            'collection_name' => 'logo',
            'name' => 'logo',
            'file_name' => 'logo.webp',
            'mime_type' => 'image/webp',
            'disk' => 'public',
            'size' => 1,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => ['avatar_32' => true],
            'responsive_images' => [],
        ]);
        $media->setRelation('model', $user);

        $conversion = Conversion::create('avatar_32');

        app(NotifyUserAvatarReady::class)->handle(new ConversionHasBeenCompletedEvent($media, $conversion));

        Event::assertNotDispatched(UserAvatarReady::class);
    }
}
