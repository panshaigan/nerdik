<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDisplayIdentityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function display_name_returns_nickname_only(): void
    {
        $user = User::factory()->create([
            'name' => 'Real Name',
            'nickname' => 'cool_handle',
        ]);

        $this->assertSame('cool_handle', $user->displayName());
    }

    #[Test]
    public function filament_name_uses_display_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Real Name',
            'nickname' => 'cool_handle',
        ]);

        $this->assertSame('cool_handle', $user->getFilamentName());
    }

    #[Test]
    public function generate_unique_nickname_from_email_returns_base_when_free(): void
    {
        // Str::slug strips dots, so 'john.doe' -> 'johndoe'.
        $this->assertSame('johndoe', User::generateUniqueNicknameFromEmail('john.doe@example.com'));
    }

    #[Test]
    public function generate_unique_nickname_from_email_appends_suffix_on_collision(): void
    {
        User::factory()->create(['nickname' => 'johndoe']);

        $this->assertSame('johndoe_2', User::generateUniqueNicknameFromEmail('john.doe@another.com'));
    }

    #[Test]
    public function generate_unique_nickname_from_email_increments_until_free(): void
    {
        User::factory()->create(['nickname' => 'jane']);
        User::factory()->create(['nickname' => 'jane_2']);
        User::factory()->create(['nickname' => 'jane_3']);

        $this->assertSame('jane_4', User::generateUniqueNicknameFromEmail('jane@example.com'));
    }

    #[Test]
    public function generate_unique_nickname_replaces_underscored_local_part_separators(): void
    {
        // Underscores in the local-part are preserved by Str::slug with '_' separator.
        $this->assertSame('jane_doe', User::generateUniqueNicknameFromEmail('jane_doe@example.com'));
    }

    #[Test]
    public function generate_unique_nickname_falls_back_to_user_when_local_part_is_empty(): void
    {
        $this->assertSame('user', User::generateUniqueNicknameFromEmail('@example.com'));
    }
}
