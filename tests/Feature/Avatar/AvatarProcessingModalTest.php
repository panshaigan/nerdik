<?php

declare(strict_types=1);

namespace Tests\Feature\Avatar;

use App\Actions\Avatars\AttachUserAvatarFromPath;
use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\TestCase;

final class AvatarProcessingModalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_avatar_processing_modal_opens_when_conversions_are_queued(): void
    {
        Queue::fake();
        config([
            'media.queue_conversions' => true,
            'media-library.queue_conversions_by_default' => true,
            'media-library.queue_connection_name' => 'database',
        ]);

        Storage::fake('public');
        $user = User::factory()->create();
        $user->profile()->update(['avatar_source' => AvatarSource::Uploaded]);

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('photo.jpg', 512, 512);

        Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'uploaded')
            ->set('croppedAvatar', $file)
            ->call('updateAvatar')
            ->assertHasNoErrors()
            ->assertSet('avatarProcessingModalOpen', true)
            ->assertSee(__('ui.profile.avatar_processing_title'))
            ->assertDispatched('profile-avatar-updated', function (string $eventName, array $params): bool {
                return isset($params['avatarUrl'])
                    && is_string($params['avatarUrl'])
                    && ! str_contains($params['avatarUrl'], 'ui-avatars.com');
            });

        Queue::assertPushed(PerformConversionsJob::class);
    }

    #[Test]
    public function test_avatar_processing_modal_closes_when_conversions_are_ready(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/modal-close.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        app(AttachUserAvatarFromPath::class)($user, $tempPath);
        $user->profile()->update(['avatar_source' => AvatarSource::Uploaded]);

        $this->actingAs($user);

        Volt::test('profile.update-avatar-form')
            ->set('avatarProcessingModalOpen', true)
            ->dispatch('profile-avatar-updated')
            ->assertSet('avatarProcessingModalOpen', false);
    }

    #[Test]
    public function test_avatar_processing_modal_can_be_dismissed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-avatar-form')
            ->set('avatarProcessingModalOpen', true)
            ->call('dismissAvatarProcessingModal')
            ->assertSet('avatarProcessingModalOpen', false);
    }
}
