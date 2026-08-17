<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Users;

use App\Actions\Users\CreateAdminUser;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_verified_admin_with_a_profile(): void
    {
        $user = app(CreateAdminUser::class)(
            email: 'Admin@Example.com',
            password: 'password',
            nickname: 'siteadmin',
            name: 'Site Admin',
        );

        $this->assertSame('admin@example.com', $user->email);
        $this->assertSame('siteadmin', $user->nickname);
        $this->assertSame('Site Admin', $user->name);
        $this->assertTrue($user->is_admin);
        $this->assertFalse($user->is_event_organizer);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertNotNull($user->profile);
        $this->assertTrue($user->profile()->exists());

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getId')->andReturn('admin');
        $this->assertTrue($user->canAccessPanel($panel));
    }

    #[Test]
    public function it_rejects_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->expectException(ValidationException::class);

        app(CreateAdminUser::class)(
            email: 'admin@example.com',
            password: 'password',
            nickname: 'otheradmin',
        );
    }
}
