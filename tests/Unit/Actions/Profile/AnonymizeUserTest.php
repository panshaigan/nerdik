<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Profile;

use App\Actions\Profile\AnonymizeUser;
use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnonymizeUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anonymize_clears_pii_oauth_flags_and_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'Real Name',
            'email' => 'real@example.com',
            'pending_email' => 'pending@example.com',
            'email_verified_at' => now(),
            'is_admin' => true,
            'is_event_organizer' => true,
            'remember_token' => 'remember-me',
        ]);

        $user->profile()->update([
            'google_id' => 'g-1',
            'facebook_id' => 'f-1',
            'discord_id' => 'd-1',
            'google_avatar_url' => 'https://example.com/g.png',
            'facebook_avatar_url' => 'https://example.com/f.png',
            'discord_avatar_url' => 'https://example.com/d.png',
            'google_email' => 'g@example.com',
            'facebook_email' => 'f@example.com',
            'discord_email' => 'd@example.com',
            'google_data' => ['sub' => 'g-1'],
            'facebook_data' => ['id' => 'f-1'],
            'discord_data' => ['id' => 'd-1'],
            'verified_email' => 'verified@example.com',
            'avatar_path' => 'avatars/old.png',
            'avatar_cache_signature' => 'sig',
            'avatar_source' => AvatarSource::Uploaded,
            'avatar_bg_color' => '#111111',
            'avatar_text_color' => '#ffffff',
            'avatar_initials' => 'RN',
            'discord_handle' => 'real#1234',
            'current_location' => 'Warsaw',
            'languages' => ['en', 'pl'],
            'notification_preferences' => ['mail' => true],
            'show_contact_email' => true,
            'show_contact_facebook' => true,
            'show_contact_google' => true,
            'show_contact_discord' => true,
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 128, 128);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/anonymize.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        $user->addMedia($tempPath)
            ->preservingOriginal()
            ->toMediaCollection('avatar');

        $this->assertSame(1, $user->getMedia('avatar')->count());

        app(AnonymizeUser::class)($user);

        $fresh = $user->fresh();
        $profile = $fresh->profile;

        $this->assertTrue($fresh->is_deleted);
        $this->assertNull($fresh->name);
        $this->assertSame('deleted+'.$user->id.'@deleted.invalid', $fresh->email);
        $this->assertNull($fresh->pending_email);
        $this->assertNull($fresh->email_verified_at);
        $this->assertNull($fresh->remember_token);
        $this->assertFalse($fresh->is_admin);
        $this->assertFalse($fresh->is_event_organizer);

        $this->assertNull($profile->google_id);
        $this->assertNull($profile->facebook_id);
        $this->assertNull($profile->discord_id);
        $this->assertNull($profile->google_avatar_url);
        $this->assertNull($profile->facebook_avatar_url);
        $this->assertNull($profile->discord_avatar_url);
        $this->assertNull($profile->google_email);
        $this->assertNull($profile->facebook_email);
        $this->assertNull($profile->discord_email);
        $this->assertNull($profile->google_data);
        $this->assertNull($profile->facebook_data);
        $this->assertNull($profile->discord_data);
        $this->assertNull($profile->verified_email);
        $this->assertNull($profile->avatar_path);
        $this->assertNull($profile->avatar_cache_signature);
        $this->assertSame(AvatarSource::Generated, $profile->avatar_source);
        $this->assertNull($profile->gallery_media_id);
        $this->assertNull($profile->avatar_bg_color);
        $this->assertNull($profile->avatar_text_color);
        $this->assertNull($profile->avatar_initials);
        $this->assertNull($profile->discord_handle);
        $this->assertNull($profile->current_location);
        $this->assertNull($profile->languages);
        $this->assertNull($profile->notification_preferences);
        $this->assertFalse($profile->show_contact_email);
        $this->assertFalse($profile->show_contact_facebook);
        $this->assertFalse($profile->show_contact_google);
        $this->assertFalse($profile->show_contact_discord);

        $this->assertSame(0, $fresh->getMedia('avatar')->count());
        $this->assertSame(0, $fresh->getMedia('source')->count());
        $this->assertSame(0, $fresh->getMedia('gallery')->count());
    }
}
