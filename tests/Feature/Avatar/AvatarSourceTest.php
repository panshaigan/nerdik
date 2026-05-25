<?php

declare(strict_types=1);

namespace Tests\Feature\Avatar;

use App\Enums\AvatarSource;
use App\Listeners\RefreshUserAvatarCache;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AvatarSourceTest extends TestCase
{
    use RefreshDatabase;

    private static function tinyPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', false) ?: '';
    }

    #[Test]
    public function test_user_can_save_uploaded_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('photo.jpg', 640, 480);

        $component = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'uploaded')
            ->set('croppedAvatar', $file)
            ->call('updateAvatar');

        $component
            ->assertHasNoErrors()
            ->assertDispatched('profile-avatar-updated', function (string $eventName, array $params): bool {
                return isset($params['avatarUrl'])
                    && is_string($params['avatarUrl'])
                    && str_contains($params['avatarUrl'], 'v=');
            });

        $user->refresh();
        $this->assertSame(AvatarSource::Uploaded, $user->profile?->avatar_source);
        $this->assertSame('avatars/'.$user->id.'.webp', $user->profile?->avatar_path);
        Storage::disk('public')->assertExists('avatars/'.$user->id.'.webp');
    }

    #[Test]
    public function test_clear_cropped_avatar_resets_pending_upload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $file = UploadedFile::fake()->image('photo.jpg', 64, 64);

        $component = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'uploaded')
            ->set('croppedAvatar', $file)
            ->call('clearCroppedAvatar');

        $component->assertSet('croppedAvatar', null);
    }

    #[Test]
    public function test_generated_avatar_initials_must_be_one_to_three_letters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'generated')
            ->set('avatar_bg_color', '#112233')
            ->set('avatar_text_color', '#ddeeff')
            ->set('avatar_initials', 'abc')
            ->call('updateAvatar')
            ->assertHasNoErrors();

        $this->assertSame('ABC', $user->refresh()->profile?->avatar_initials);

        Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'generated')
            ->set('avatar_bg_color', '#112233')
            ->set('avatar_text_color', '#ddeeff')
            ->set('avatar_initials', '1234')
            ->call('updateAvatar')
            ->assertHasErrors('avatar_initials');

        Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'generated')
            ->set('avatar_bg_color', '#112233')
            ->set('avatar_text_color', '#ddeeff')
            ->set('avatar_initials', 'ABCD')
            ->call('updateAvatar')
            ->assertHasErrors('avatar_initials');
    }

    #[Test]
    public function test_switching_from_gravatar_to_uploaded_requires_new_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->profile()->update([
            'avatar_source' => AvatarSource::Gravatar,
            'avatar_path' => 'avatars/'.$user->id.'.webp',
            'avatar_cache_signature' => 'old',
        ]);
        Storage::disk('public')->put('avatars/'.$user->id.'.webp', 'x');

        $this->actingAs($user);

        $component = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'uploaded')
            ->call('updateAvatar');

        $component->assertHasErrors('croppedAvatar');
    }

    #[Test]
    public function test_user_can_switch_to_gravatar_and_cache_file(): void
    {
        Storage::fake('public');
        $png = self::tinyPng();
        Http::fake([
            'https://www.gravatar.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->create([
            'email' => 'gravatar-user@example.com',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'gravatar')
            ->call('updateAvatar')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame(AvatarSource::Gravatar, $user->profile?->avatar_source);
        $this->assertNotNull($user->profile?->avatar_cache_signature);
        Storage::disk('public')->assertExists('avatars/'.$user->id.'.webp');
    }

    #[Test]
    public function test_google_avatar_save_when_linked_and_remote_ok(): void
    {
        Storage::fake('public');
        $png = self::tinyPng();
        Http::fake([
            'https://lh3.googleusercontent.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->create();
        $user->profile()->update([
            'google_id' => 'g-123',
            'google_avatar_url' => 'https://lh3.googleusercontent.com/a/fake=s512-c',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'google')
            ->call('updateAvatar')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame(AvatarSource::Google, $user->profile?->avatar_source);
        Storage::disk('public')->assertExists('avatars/'.$user->id.'.webp');
    }

    #[Test]
    public function test_google_avatar_shows_link_when_not_linked(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'google_id' => null,
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'google')
            ->assertSee('Link Google account', false);
    }

    #[Test]
    public function test_login_listener_refreshes_gravatar_cache(): void
    {
        Storage::fake('public');

        $png = self::tinyPng();
        Http::fake([
            'https://www.gravatar.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->create([
            'email' => 'listener@example.com',
        ]);
        $user->profile()->update([
            'avatar_source' => AvatarSource::Gravatar,
        ]);

        $listener = new RefreshUserAvatarCache;
        $listener->handle(new Login('web', $user, false));

        Storage::disk('public')->assertExists('avatars/'.$user->id.'.webp');
    }

    #[Test]
    public function test_login_listener_skips_when_signature_unchanged(): void
    {
        Storage::fake('public');
        $png = self::tinyPng();
        Http::fake([
            'https://www.gravatar.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $user = User::factory()->create([
            'email' => 'stable@example.com',
        ]);
        $user->profile()->update([
            'avatar_source' => AvatarSource::Gravatar,
        ]);

        $listener = new RefreshUserAvatarCache;
        $listener->handle(new Login('web', $user, false));

        $firstSig = $user->fresh()->profile?->avatar_cache_signature;
        $this->assertNotNull($firstSig);

        $listener->handle(new Login('web', $user->fresh(), false));

        $this->assertSame($firstSig, $user->fresh()->profile?->avatar_cache_signature);
    }
}
