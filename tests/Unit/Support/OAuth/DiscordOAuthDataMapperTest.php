<?php

declare(strict_types=1);

namespace Tests\Unit\Support\OAuth;

use App\Support\OAuth\DiscordOAuthDataMapper;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DiscordOAuthDataMapperTest extends TestCase
{
    private DiscordOAuthDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new DiscordOAuthDataMapper;
    }

    #[Test]
    public function it_prefers_global_name_for_display_name(): void
    {
        $socialiteUser = $this->socialiteUser([
            'id' => '123456789012345678',
            'username' => 'cooluser',
            'global_name' => 'Cool Display',
        ]);

        $mapped = $this->mapper->map($socialiteUser);

        $this->assertNotNull($mapped);
        $this->assertSame('cooluser', $mapped['username']);
        $this->assertSame('Cool Display', $mapped['global_name']);
        $this->assertSame('Cool Display', $mapped['display_name']);
        $this->assertSame('https://discord.com/users/123456789012345678', $mapped['profile_web_url']);
        $this->assertSame('discord://-/users/123456789012345678', $mapped['profile_app_url']);
    }

    #[Test]
    public function it_falls_back_to_username_for_display_name(): void
    {
        $socialiteUser = $this->socialiteUser([
            'id' => '987654321098765432',
            'username' => 'plainuser',
        ]);

        $mapped = $this->mapper->map($socialiteUser);

        $this->assertNotNull($mapped);
        $this->assertSame('plainuser', $mapped['display_name']);
    }

    #[Test]
    public function resolve_handle_returns_display_name(): void
    {
        $socialiteUser = $this->socialiteUser([
            'id' => '123456789012345678',
            'username' => 'cooluser',
            'global_name' => 'Cool Display',
        ]);

        $handle = $this->mapper->resolveHandle($socialiteUser);

        $this->assertSame('Cool Display', $handle['handle']);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function socialiteUser(array $raw): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->setRaw($raw)->map([
            'id' => $raw['id'] ?? null,
            'nickname' => $raw['username'] ?? null,
            'name' => $raw['username'] ?? null,
            'email' => $raw['email'] ?? null,
            'avatar' => null,
        ]);

        return $user;
    }
}
