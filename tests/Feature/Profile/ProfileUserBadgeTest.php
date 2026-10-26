<?php

namespace Tests\Feature\Profile;

use App\Actions\Avatars\AttachUserAvatarFromPath;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUserBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_badge_uses_ui_avatars_with_profile_colors(): void
    {
        $user = User::factory()->create([
            'nickname' => 'Color User',
        ]);
        $user->profile()->update([
            'avatar_bg_color' => '#112233',
            'avatar_text_color' => '#ddeeff',
            'avatar_initials' => 'CU',
        ]);

        $html = Blade::render('<x-user-badge :user="$user" avatar-only />', [
            'user' => $user->fresh('profile'),
        ]);

        $this->assertStringContainsString('ui-avatars.com/api/', $html);
        $this->assertStringContainsString('name=CU', $html);
        $this->assertStringContainsString('length=2', $html);
        $this->assertStringNotContainsString('name=Color%20User', $html);
        $this->assertStringContainsString('background=112233', $html);
        $this->assertStringContainsString('color=ddeeff', $html);
    }

    public function test_user_badge_uses_avatar_32_img_for_uploaded_source(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'nickname' => 'Disk User',
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/badge-user.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        app(AttachUserAvatarFromPath::class)($user, $tempPath);
        $user->profile()->update([
            'avatar_source' => 'uploaded',
            'avatar_path' => null,
        ]);

        $html = Blade::render('<x-user-badge :user="$user" avatar-only />', [
            'user' => $user->fresh(['profile', 'media']),
        ]);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('avatar_32', $html);
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringNotContainsString('srcset=', $html);
    }

    public function test_user_badge_prefers_organization_when_provided(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Org Display Name',
            'acronym' => null,
        ]);
        $user = User::factory()->create([
            'nickname' => 'User Display Name',
        ]);

        $html = Blade::render('<x-user-badge :user="$user" :organization="$organization" avatar-only />', [
            'user' => $user,
            'organization' => $organization,
        ]);

        $this->assertStringContainsString('ui-avatars.com/api/', $html);
        $this->assertStringContainsString('name=Org%20Display%20Name', $html);
        $this->assertStringNotContainsString('name=User%20Display%20Name', $html);
        $this->assertStringContainsString('alt="Org Display Name"', $html);
    }

    public function test_user_badge_shows_organization_acronym_after_nickname(): void
    {
        $organization = Organization::factory()->create([
            'acronym' => 'CGN',
        ]);
        $user = User::factory()->create([
            'nickname' => 'pixel_mage',
            'organization_id' => $organization->id,
        ]);

        $html = Blade::render('<x-user-badge :user="$user" />', [
            'user' => $user->fresh('organization'),
        ]);

        $this->assertStringContainsString('pixel_mage [CGN]', $html);
    }

    public function test_user_badge_shows_nickname_only_when_organization_has_no_acronym(): void
    {
        $organization = Organization::factory()->create([
            'acronym' => null,
        ]);
        $user = User::factory()->create([
            'nickname' => 'solo_player',
            'organization_id' => $organization->id,
        ]);

        $html = Blade::render('<x-user-badge :user="$user" />', [
            'user' => $user->fresh('organization'),
        ]);

        $this->assertStringContainsString('solo_player', $html);
        $this->assertStringNotContainsString('[', $html);
    }

    public function test_user_badge_shows_nickname_only_when_user_has_no_organization(): void
    {
        $user = User::factory()->create([
            'nickname' => 'free_agent',
            'organization_id' => null,
        ]);

        $html = Blade::render('<x-user-badge :user="$user" />', [
            'user' => $user,
        ]);

        $this->assertStringContainsString('free_agent', $html);
        $this->assertStringNotContainsString('[', $html);
    }
}
