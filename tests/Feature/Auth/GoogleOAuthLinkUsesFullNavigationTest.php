<?php

namespace Tests\Feature\Auth;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleOAuthLinkUsesFullNavigationTest extends TestCase
{
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

            $dom = new DOMDocument;
            $html = '<?xml encoding="UTF-8">'.$response->getContent();
            @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

            $xpath = new DOMXPath($dom);
            // Mary's Button uses `id` in `wire:key`, not always as HTML `id` on `<a>`; target OAuth start URL instead.
            $links = $xpath->query("//a[contains(@href, '/auth/google') and not(contains(@href, 'callback'))]");

            $this->assertSame(1, $links->length, "Expected one Google OAuth redirect anchor on {$routeName}");

            $link = $links->item(0);
            $this->assertNotNull($link);
            $this->assertFalse($link->hasAttribute('wire:navigate'), 'OAuth start URL must not use wire:navigate (full page load required).');
        }
    }
}
