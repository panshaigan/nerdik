<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use App\Support\Ui\GeneratedAvatarColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RandomizeInitialsAvatarColorsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_profiles_with_default_colors(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'avatar_bg_color' => GeneratedAvatarColors::defaultBackground(),
            'avatar_text_color' => GeneratedAvatarColors::defaultText(),
        ]);

        $this->artisan('profiles:randomize-initials-avatar-colors')
            ->assertSuccessful();

        $user->refresh();
        $this->assertFalse(GeneratedAvatarColors::usesDefaultColors(
            $user->profile?->avatar_bg_color,
            $user->profile?->avatar_text_color,
        ));
        $this->assertContains($user->profile?->avatar_bg_color, GeneratedAvatarColors::palette());
    }

    #[Test]
    public function it_updates_profiles_with_null_colors(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'avatar_bg_color' => null,
            'avatar_text_color' => null,
        ]);

        $this->artisan('profiles:randomize-initials-avatar-colors')
            ->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->profile?->avatar_bg_color);
        $this->assertContains($user->profile?->avatar_bg_color, GeneratedAvatarColors::palette());
    }

    #[Test]
    public function it_leaves_custom_colors_unchanged(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'avatar_bg_color' => '#112233',
            'avatar_text_color' => '#ddeeff',
        ]);

        $this->artisan('profiles:randomize-initials-avatar-colors')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame('#112233', $user->profile?->avatar_bg_color);
        $this->assertSame('#ddeeff', $user->profile?->avatar_text_color);
    }

    #[Test]
    public function dry_run_does_not_persist_changes(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'avatar_bg_color' => GeneratedAvatarColors::defaultBackground(),
            'avatar_text_color' => GeneratedAvatarColors::defaultText(),
        ]);

        $this->artisan('profiles:randomize-initials-avatar-colors', ['--dry-run' => true])
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(GeneratedAvatarColors::usesDefaultColors(
            $user->profile?->avatar_bg_color,
            $user->profile?->avatar_text_color,
        ));
    }
}
