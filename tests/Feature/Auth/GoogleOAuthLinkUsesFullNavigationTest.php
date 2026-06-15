<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleOAuthLinkUsesFullNavigationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function google_login_and_register_links_do_not_use_wire_navigate(): void
    {
        config([
            'services.google.client_id' => 'stub-client.apps.googleusercontent.com',
            'services.google.client_secret' => 'stub-secret',
        ]);

        foreach (['login', 'register'] as $routeName) {
            $response = $this->get(route($routeName));
            $response->assertOk();

            $this->assertOAuthLinkDoesNotUseWireNavigate(
                $response->getContent(),
                '/auth/google',
                $routeName,
            );
        }
    }

    #[Test]
    public function profile_avatar_google_link_does_not_use_wire_navigate(): void
    {
        config([
            'services.google.client_id' => 'stub-client.apps.googleusercontent.com',
            'services.google.client_secret' => 'stub-secret',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $html = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'google')
            ->html();

        $this->assertOAuthLinkDoesNotUseWireNavigate($html, '/auth/google', 'profile avatar');
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
