<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_an_admin_from_options_without_wiping(): void
    {
        $existing = User::factory()->create();

        $this->artisan('user:create-admin', [
            '--email' => 'admin@example.com',
            '--nickname' => 'siteadmin',
            '--password' => 'password',
        ])
            ->expectsOutputToContain('Admin account created for admin@example.com (siteadmin).')
            ->assertSuccessful();

        $this->assertModelExists($existing);

        $user = User::query()->where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->profile);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertDatabaseCount(User::class, 2);
    }

    #[Test]
    public function it_prompts_for_credentials_when_options_are_missing(): void
    {
        $this->artisan('user:create-admin')
            ->expectsQuestion('Admin email', 'admin@example.com')
            ->expectsQuestion('Admin nickname', 'siteadmin')
            ->expectsQuestion('Admin password', 'password')
            ->expectsQuestion('Confirm password', 'password')
            ->assertSuccessful();

        $this->assertTrue(
            User::query()->where('email', 'admin@example.com')->where('is_admin', true)->exists(),
        );
    }

    #[Test]
    public function it_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('user:create-admin', [
            '--email' => 'admin@example.com',
            '--nickname' => 'siteadmin',
            '--password' => 'password',
        ])
            ->expectsOutputToContain('The email has already been taken.')
            ->assertFailed();
    }
}
