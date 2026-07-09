<?php

namespace Tests\Feature\Broadcast;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EchoClientConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_layout_injects_echo_client_config_when_broadcasting_is_configured(): void
    {
        config([
            'broadcasting.echo_client' => [
                'key' => 'test-echo-key',
                'host' => 'nerdik.app',
                'port' => 443,
                'scheme' => 'https',
            ],
        ]);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('window.__nerdikEchoConfig', false);
        $response->assertSee('test-echo-key', false);
        $response->assertSee('nerdik.app', false);
    }

    public function test_guest_layout_omits_echo_client_config_when_not_configured(): void
    {
        config([
            'broadcasting.echo_client' => null,
        ]);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('window.__nerdikEchoConfig', false);
    }

    public function test_welcome_page_injects_echo_client_config_when_broadcasting_is_configured(): void
    {
        config([
            'broadcasting.echo_client' => [
                'key' => 'staging-echo-key',
                'host' => 'staging.nerdik.app',
                'port' => 443,
                'scheme' => 'https',
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('window.__nerdikEchoConfig', false);
        $response->assertSee('staging-echo-key', false);
        $response->assertSee('staging.nerdik.app', false);
    }
}
