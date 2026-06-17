<?php

declare(strict_types=1);

namespace Tests\Unit\Support\OAuth;

use App\Support\OAuth\GoogleOAuthDataMapper;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GoogleOAuthDataMapperTest extends TestCase
{
    private GoogleOAuthDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new GoogleOAuthDataMapper;
    }

    #[Test]
    public function it_maps_name_and_locale_fields(): void
    {
        $socialiteUser = $this->socialiteUser([
            'sub' => 'google-subject',
            'given_name' => 'Jane',
            'family_name' => 'Doe',
            'locale' => 'en',
        ]);

        $mapped = $this->mapper->map($socialiteUser);

        $this->assertNotNull($mapped);
        $this->assertSame('Jane', $mapped['given_name']);
        $this->assertSame('Doe', $mapped['family_name']);
        $this->assertSame('en', $mapped['locale']);
    }

    #[Test]
    public function it_returns_null_when_no_useful_fields_present(): void
    {
        $socialiteUser = $this->socialiteUser([
            'sub' => 'google-subject',
            'email' => 'jane@example.com',
        ]);

        $this->assertNull($this->mapper->map($socialiteUser));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function socialiteUser(array $raw): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->setRaw($raw)->map([
            'id' => $raw['sub'] ?? null,
            'name' => $raw['name'] ?? null,
            'email' => $raw['email'] ?? null,
            'avatar' => null,
        ]);

        return $user;
    }
}
