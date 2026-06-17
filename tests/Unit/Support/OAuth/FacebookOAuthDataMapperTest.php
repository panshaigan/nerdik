<?php

declare(strict_types=1);

namespace Tests\Unit\Support\OAuth;

use App\Support\OAuth\FacebookOAuthDataMapper;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FacebookOAuthDataMapperTest extends TestCase
{
    private FacebookOAuthDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new FacebookOAuthDataMapper;
    }

    #[Test]
    public function it_maps_vanity_profile_link_from_graph_api_to_contact_urls(): void
    {
        $socialiteUser = $this->socialiteUser([
            'id' => 'app-scoped-id',
            'name' => 'Mark Zuckerberg',
        ]);

        $mapped = $this->mapper->map($socialiteUser, [
            'name' => 'Mark Zuckerberg',
            'link' => 'https://www.facebook.com/zuck',
        ]);

        $this->assertNotNull($mapped);
        $this->assertSame('https://www.facebook.com/zuck', $mapped['profile_url']);
        $this->assertSame('zuck', $mapped['vanity']);
        $this->assertSame('https://m.me/zuck', $mapped['messenger_url']);
        $this->assertSame('https://www.facebook.com/messages/t/zuck', $mapped['messages_url']);
        $this->assertSame('Mark Zuckerberg', $mapped['name']);
    }

    #[Test]
    public function it_maps_numeric_profile_link_from_graph_api_to_contact_urls(): void
    {
        $socialiteUser = $this->socialiteUser([
            'id' => 'app-scoped-id',
            'name' => 'Jane Doe',
        ]);

        $mapped = $this->mapper->map($socialiteUser, [
            'name' => 'Jane Doe',
            'link' => 'https://www.facebook.com/profile.php?id=4',
        ]);

        $this->assertNotNull($mapped);
        $this->assertSame('https://www.facebook.com/profile.php?id=4', $mapped['profile_url']);
        $this->assertSame('4', $mapped['public_id']);
        $this->assertNull($mapped['vanity']);
        $this->assertSame('https://m.me/4', $mapped['messenger_url']);
        $this->assertSame('https://www.facebook.com/messages/t/4', $mapped['messages_url']);
    }

    #[Test]
    public function it_does_not_build_profile_url_without_graph_link(): void
    {
        $socialiteUser = $this->socialiteUser([
            'id' => '28431023993164505',
            'name' => 'Jane Doe',
        ]);

        $mapped = $this->mapper->map($socialiteUser, [
            'name' => 'Jane Doe',
            'link' => null,
        ]);

        $this->assertNotNull($mapped);
        $this->assertNull($mapped['profile_url']);
        $this->assertNull($mapped['public_id']);
        $this->assertSame('Jane Doe', $mapped['name']);
    }

    #[Test]
    public function it_returns_null_when_graph_profile_is_missing_name_and_link(): void
    {
        $socialiteUser = $this->socialiteUser([]);

        $this->assertNull($this->mapper->map($socialiteUser, null));
        $this->assertNull($this->mapper->map($socialiteUser, [
            'name' => null,
            'link' => null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function socialiteUser(array $raw): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->setRaw($raw)->map([
            'id' => $raw['id'] ?? null,
            'name' => $raw['name'] ?? null,
            'email' => $raw['email'] ?? null,
            'avatar' => null,
        ]);

        return $user;
    }
}
