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
            ->assertSeeHtml('id="ui-image-crop-modal"')
            ->assertSeeHtml('class="modal backdrop-blur"')
            ->assertSeeHtml('ui-modal-surface')
            ->assertSeeHtml('data-image-crop-croppie')
            ->assertSeeHtml('ui-image-crop-crop');
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
        $this->assertStringContainsString('data-image-crop-file-trigger', $html);
        $this->assertStringContainsString('data-image-crop-remove', $html);
        $this->assertStringContainsString('data-default-src', $html);
        $this->assertMatchesRegularExpression(
            '/id="ui-profile-avatar-form"[\s\S]*?type="submit"[\s\S]*?<\/form>\s*<\/section>/',
            $html,
        );
        $this->assertStringNotContainsString('id="ui-image-crop-modal"', $html);
    }
}
