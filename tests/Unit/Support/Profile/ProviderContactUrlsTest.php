<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Profile;

use App\Models\UserProfile;
use App\Support\Profile\ProviderContactUrls;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProviderContactUrlsTest extends TestCase
{
    private ProviderContactUrls $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ProviderContactUrls;
    }

    #[Test]
    public function facebook_prefers_stored_public_id_when_profile_url_missing(): void
    {
        $profile = $this->profileWithFacebookData([
            'public_id' => '1234567890',
        ]);

        $urls = $this->resolver->facebook($profile);

        $this->assertSame([
            'profileUrl' => 'https://www.facebook.com/profile.php?id=1234567890',
            'messagesUrl' => 'https://www.facebook.com/messages/t/1234567890',
            'messengerUrl' => 'https://m.me/1234567890',
        ], $urls);
    }

    #[Test]
    public function facebook_prefers_stored_provider_data_urls(): void
    {
        $profile = $this->profileWithFacebookData([
            'profile_url' => 'https://www.facebook.com/zuck',
            'messages_url' => 'https://www.facebook.com/messages/t/zuck',
            'messenger_url' => 'https://m.me/zuck',
        ]);

        $urls = $this->resolver->facebook($profile);

        $this->assertSame([
            'profileUrl' => 'https://www.facebook.com/zuck',
            'messagesUrl' => 'https://www.facebook.com/messages/t/zuck',
            'messengerUrl' => 'https://m.me/zuck',
        ], $urls);
    }

    #[Test]
    public function facebook_falls_back_to_legacy_id_urls_when_provider_data_missing(): void
    {
        $profile = $this->profileWithFacebookData(null);

        $urls = $this->resolver->facebook($profile);

        $this->assertSame([
            'profileUrl' => 'https://www.facebook.com/profile.php?id=app-scoped-id',
            'messagesUrl' => 'https://www.facebook.com/messages/t/app-scoped-id',
            'messengerUrl' => 'https://m.me/app-scoped-id',
        ], $urls);
    }

    #[Test]
    public function facebook_prefers_user_profile_url_over_oauth_profile_url(): void
    {
        $profile = $this->profileWithFacebookData([
            'profile_url' => 'https://www.facebook.com/zuck',
            'messages_url' => 'https://www.facebook.com/messages/t/zuck',
            'messenger_url' => 'https://m.me/zuck',
        ]);
        $profile->forceFill([
            'facebook_profile_url' => 'https://www.facebook.com/janedoe',
        ]);

        $urls = $this->resolver->facebook($profile);

        $this->assertSame([
            'profileUrl' => 'https://www.facebook.com/janedoe',
            'messagesUrl' => 'https://www.facebook.com/messages/t/zuck',
            'messengerUrl' => 'https://m.me/zuck',
        ], $urls);
    }

    #[Test]
    public function facebook_uses_manual_profile_url_without_oauth_link(): void
    {
        $profile = (new UserProfile)->forceFill([
            'facebook_id' => null,
            'facebook_data' => null,
            'facebook_profile_url' => 'https://www.facebook.com/manual-only',
        ]);

        $urls = $this->resolver->facebook($profile);

        $this->assertSame([
            'profileUrl' => 'https://www.facebook.com/manual-only',
            'messagesUrl' => 'https://www.facebook.com/messages/t/manual-only',
            'messengerUrl' => 'https://m.me/manual-only',
        ], $urls);
    }

    #[Test]
    public function discord_prefers_stored_provider_data_urls(): void
    {
        $profile = $this->profileWithDiscordData([
            'profile_web_url' => 'https://discord.com/users/111',
            'profile_app_url' => 'discord://-/users/111',
        ]);

        $urls = $this->resolver->discord($profile);

        $this->assertSame([
            'webUrl' => 'https://discord.com/users/111',
            'appUrl' => 'discord://-/users/111',
        ], $urls);
    }

    #[Test]
    public function discord_falls_back_to_legacy_id_urls_when_provider_data_missing(): void
    {
        $profile = $this->profileWithDiscordData(null);

        $urls = $this->resolver->discord($profile);

        $this->assertSame([
            'webUrl' => 'https://discord.com/users/67890',
            'appUrl' => 'discord://-/users/67890',
        ], $urls);
    }

    /**
     * @param  array<string, string>|null  $facebookData
     */
    private function profileWithFacebookData(?array $facebookData): UserProfile
    {
        return (new UserProfile)->forceFill([
            'facebook_id' => 'app-scoped-id',
            'facebook_data' => $facebookData,
        ]);
    }

    /**
     * @param  array<string, string>|null  $discordData
     */
    private function profileWithDiscordData(?array $discordData): UserProfile
    {
        return (new UserProfile)->forceFill([
            'discord_id' => '67890',
            'discord_data' => $discordData,
        ]);
    }
}
