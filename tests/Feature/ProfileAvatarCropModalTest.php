<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Profile\ProfileTabs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSeeHtml('id="ui-profile-avatar-crop-modal"')
            ->assertSeeHtml('class="modal backdrop-blur"')
            ->assertSeeHtml('ui-modal-surface')
            ->assertSeeHtml('data-profile-avatar-croppie')
            ->assertSeeHtml('ui-profile-avatar-crop');
    }

    #[Test]
    public function test_avatar_form_has_preview_hook_and_intact_submit_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $html = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'uploaded')
            ->html();

        $this->assertStringContainsString('data-profile-avatar-preview', $html);
        $this->assertStringContainsString('data-profile-avatar-dropzone', $html);
        $this->assertStringContainsString('data-profile-avatar-file-trigger', $html);
        $this->assertStringContainsString('data-profile-avatar-remove', $html);
        $this->assertStringNotContainsString('data-profile-avatar-file-name', $html);
        $this->assertStringContainsString('data-default-src', $html);
        $this->assertMatchesRegularExpression(
            '/id="ui-profile-avatar-form"[\s\S]*?type="submit"[\s\S]*?<\/form>\s*<\/section>/',
            $html,
        );
        $this->assertStringNotContainsString('id="ui-profile-avatar-crop-modal"', $html);
    }
}
