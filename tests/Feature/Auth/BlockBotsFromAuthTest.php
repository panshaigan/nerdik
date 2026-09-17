<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockBotsFromAuthTest extends TestCase
{
    private const string AMAZONBOT_UA = 'Mozilla/5.0 (compatible; Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot)';

    private const string BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * @return array<string, array{0: string}>
     */
    public static function authRoutesProvider(): array
    {
        return [
            'login' => ['login'],
            'register' => ['register'],
            'password request' => ['password.request'],
            'google redirect' => ['google.redirect'],
            'google callback' => ['google.callback'],
            'facebook redirect' => ['facebook.redirect'],
            'facebook callback' => ['facebook.callback'],
            'discord redirect' => ['discord.redirect'],
            'discord callback' => ['discord.callback'],
        ];
    }

    #[Test]
    #[DataProvider('authRoutesProvider')]
    public function known_crawlers_are_forbidden_on_auth_routes(string $routeName): void
    {
        $this->withHeader('User-Agent', self::AMAZONBOT_UA)
            ->get(route($routeName))
            ->assertForbidden();
    }

    #[Test]
    public function normal_browsers_can_reach_login(): void
    {
        $this->withHeader('User-Agent', self::BROWSER_UA)
            ->get(route('login'))
            ->assertOk();
    }

    #[Test]
    public function robots_txt_disallows_auth_paths(): void
    {
        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertSee('Disallow: /login', false);
        $response->assertSee('Disallow: /register', false);
        $response->assertSee('Disallow: /auth/', false);
        $response->assertSee('User-agent: Amazonbot', false);
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }
}
