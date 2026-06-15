<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacebookOAuthLinkUsesFullNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function facebook_login_and_register_links_do_not_use_wire_navigate(): void
    {
        config([
            'services.facebook.client_id' => 'stub-fb-client-id',
            'services.facebook.client_secret' => 'stub-fb-secret',
        ]);

        foreach (['login', 'register'] as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertOk();

            $this->assertOAuthLinkDoesNotUseWireNavigate(
                $response->getContent(),
                '/auth/facebook',
                $routeName,
            );
        }
    }

    #[Test]
    public function profile_avatar_facebook_link_does_not_use_wire_navigate(): void
    {
        config([
            'services.facebook.client_id' => 'stub-fb-client-id',
            'services.facebook.client_secret' => 'stub-fb-secret',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $html = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'facebook')
            ->html();

        $this->assertOAuthLinkDoesNotUseWireNavigate($html, '/auth/facebook', 'profile avatar');
    }

    private function assertOAuthLinkDoesNotUseWireNavigate(string $html, string $pathFragment, string $context): void
    {
        $dom = new DOMDocument;
        $html = '<?xml encoding="UTF-8">'.$html;
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $links = $xpath->query("//a[contains(@href, '{$pathFragment}') and not(contains(@href, 'callback'))]");

        $this->assertSame(1, $links->length, "Expected one OAuth redirect anchor on {$context}");

        $link = $links->item(0);
        $this->assertNotNull($link);
        $this->assertFalse($link->hasAttribute('wire:navigate'), 'OAuth start URL must not use wire:navigate (full page load required).');
    }
}
