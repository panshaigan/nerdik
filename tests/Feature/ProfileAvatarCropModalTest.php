<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Avatars\StoreUploadedAvatar;
use App\Enums\AvatarSource;
use App\Livewire\Profile\ProfileTabs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProfileAvatarCropModalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_profile_tabs_render_crop_modal_outside_avatar_tab_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::withoutLazyLoading()
            ->test(ProfileTabs::class)
            ->set('tab', 'avatar')
            ->assertSeeHtml('id="ui-image-crop-modal"')
            ->assertSeeHtml('class="modal backdrop-blur z-[100030]"')
            ->assertSeeHtml('ui-modal-surface')
            ->assertSeeHtml('data-image-crop-stage')
            ->assertSeeHtml('data-image-crop-zoom')
            ->assertSeeHtml('ui-image-crop-stage');
    }

    #[Test]
    public function test_avatar_form_has_preview_hook_and_intact_submit_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $html = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'uploaded')
            ->html();

        $this->assertStringContainsString('data-image-crop-preview', $html);
        $this->assertStringContainsString('data-image-crop-dropzone', $html);
        $this->assertStringContainsString('data-image-crop-source-wire-property="sourceImage"', $html);
        $this->assertStringContainsString('data-image-crop-source-clear-method="clearSourceImage"', $html);
        $this->assertStringContainsString('data-image-crop-file-trigger', $html);
        $this->assertStringContainsString('data-image-crop-remove', $html);
        $this->assertStringContainsString('data-image-crop-recrop-saved', $html);
        $this->assertStringContainsString('data-default-src', $html);
        $this->assertStringNotContainsString('data-image-crop-source-url=', $html);
        $this->assertMatchesRegularExpression(
            '/id="ui-profile-avatar-form"[\s\S]*?type="submit"[\s\S]*?<\/form>[\s\S]*?<\/section>/',
            $html,
        );
        $this->assertStringNotContainsString('id="ui-image-crop-modal"', $html);
    }

    #[Test]
    public function test_avatar_form_exposes_saved_source_url_for_recrop(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->profile()->update(['avatar_source' => AvatarSource::Uploaded]);

        app(StoreUploadedAvatar::class)(
            $user,
            UploadedFile::fake()->image('avatar.jpg', 640, 480),
            UploadedFile::fake()->image('original.jpg', 640, 360),
        );

        $sourceUrl = $user->fresh()->cropSourceImageUrl();
        $this->assertNotNull($sourceUrl);

        $this->actingAs($user);

        $html = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'uploaded')
            ->html();

        $this->assertStringContainsString('data-image-crop-source-url="'.e($sourceUrl).'"', $html);
        $this->assertStringContainsString('data-image-crop-recrop-saved', $html);
        $this->assertStringContainsString('data-image-crop-recrop-saved-hint', $html);
    }
}
