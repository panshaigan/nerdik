<?php

namespace Tests\Feature\Auth;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FacebookOAuthLinkUsesFullNavigationTest extends TestCase
{
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

            $dom = new DOMDocument;
            $html = '<?xml encoding="UTF-8">'.$response->getContent();
            @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

            $xpath = new DOMXPath($dom);
            $links = $xpath->query("//a[contains(@href, '/auth/facebook') and not(contains(@href, 'callback'))]");

            $this->assertSame(1, $links->length, "Expected one Facebook OAuth redirect anchor on {$routeName}");

            $link = $links->item(0);
            $this->assertNotNull($link);
            $this->assertFalse($link->hasAttribute('wire:navigate'), 'OAuth start URL must not use wire:navigate (full page load required).');
        }
    }
}
