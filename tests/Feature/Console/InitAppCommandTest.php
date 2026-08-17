<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\App\BootstrapProductionState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InitAppCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_aborts_when_confirmation_is_declined(): void
    {
        $this->mock(BootstrapProductionState::class, function ($mock): void {
            $mock->shouldNotReceive('__invoke');
        });

        $this->artisan('app:init')
            ->expectsConfirmation('This will drop all tables and leftover media files. Continue?', 'no')
            ->expectsOutputToContain('Aborted.')
            ->assertFailed();

        $this->assertDatabaseCount(User::class, 0);
    }

    #[Test]
    public function it_requires_force_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->mock(BootstrapProductionState::class, function ($mock): void {
            $mock->shouldNotReceive('__invoke');
        });

        $this->artisan('app:init')
            ->expectsOutputToContain('The --force option is required in production.')
            ->assertFailed();

        $this->assertDatabaseCount(User::class, 0);
    }

    #[Test]
    public function it_requires_force_when_non_interactive(): void
    {
        $this->mock(BootstrapProductionState::class, function ($mock): void {
            $mock->shouldNotReceive('__invoke');
        });

        $this->artisan('app:init', [
            '--no-interaction' => true,
            '--email' => 'admin@example.com',
            '--nickname' => 'admin',
            '--password' => 'password',
        ])
            ->expectsOutputToContain('The --force option is required when running non-interactively.')
            ->assertFailed();

        $this->assertDatabaseCount(User::class, 0);
    }

    #[Test]
    public function it_creates_an_admin_after_bootstrap(): void
    {
        $this->mock(BootstrapProductionState::class, function ($mock): void {
            $mock->shouldReceive('__invoke')->once();
        });

        $this->artisan('app:init', [
            '--email' => 'admin@example.com',
            '--nickname' => 'siteadmin',
            '--password' => 'password',
        ])
            ->expectsConfirmation('This will drop all tables and leftover media files. Continue?', 'yes')
            ->expectsOutputToContain('Admin account created for admin@example.com (siteadmin).')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('siteadmin', $user->nickname);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->profile);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    #[Test]
    public function it_prompts_for_credentials_when_options_are_missing(): void
    {
        $this->mock(BootstrapProductionState::class, function ($mock): void {
            $mock->shouldReceive('__invoke')->once();
        });

        $this->artisan('app:init')
            ->expectsConfirmation('This will drop all tables and leftover media files. Continue?', 'yes')
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
    public function it_does_not_bootstrap_when_passwords_do_not_match(): void
    {
        $this->mock(BootstrapProductionState::class, function ($mock): void {
            $mock->shouldNotReceive('__invoke');
        });

        $this->artisan('app:init', [
            '--email' => 'admin@example.com',
            '--nickname' => 'siteadmin',
        ])
            ->expectsConfirmation('This will drop all tables and leftover media files. Continue?', 'yes')
            ->expectsQuestion('Admin password', 'password')
            ->expectsQuestion('Confirm password', 'different')
            ->expectsOutputToContain('Passwords do not match.')
            ->assertFailed();

        $this->assertDatabaseCount(User::class, 0);
    }

    #[Test]
    public function it_warns_about_backups_in_production(): void
    {
        $this->app['env'] = 'production';
        Password::defaults(fn (): Password => Password::min(8));

        $this->mock(BootstrapProductionState::class, function ($mock): void {
            $mock->shouldReceive('__invoke')->once();
        });

        $this->artisan('app:init', [
            '--force' => true,
            '--email' => 'admin@example.com',
            '--nickname' => 'siteadmin',
            '--password' => 'password',
        ])
            ->expectsOutputToContain('Consider running `make backup-prod` before continuing.')
            ->expectsConfirmation('This will drop all tables and leftover media files. Continue?', 'yes')
            ->assertSuccessful();
    }

    #[Test]
    public function it_runs_non_interactively_with_force_and_credential_options(): void
    {
        $this->mock(BootstrapProductionState::class, function ($mock): void {
            $mock->shouldReceive('__invoke')->once();
        });

        $this->artisan('app:init', [
            '--no-interaction' => true,
            '--force' => true,
            '--email' => 'admin@example.com',
            '--nickname' => 'siteadmin',
            '--password' => 'password',
        ])
            ->assertSuccessful();

        $this->assertTrue(
            User::query()->where('email', 'admin@example.com')->where('is_admin', true)->exists(),
        );
    }
}
