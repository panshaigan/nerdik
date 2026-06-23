<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DisplayTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function bindRequest(Request $request): void
    {
        app()->instance('request', $request);
    }

    public function test_guest_without_cookie_falls_back_to_europe_warsaw(): void
    {
        $this->bindRequest(Request::create('/'));

        $this->assertSame('Europe/Warsaw', display_timezone());
    }

    public function test_guest_with_valid_cookie_uses_browser_timezone(): void
    {
        $this->bindRequest(Request::create('/', 'GET', [], ['browser_timezone' => 'America/New_York']));

        $this->assertSame('America/New_York', display_timezone());
    }

    public function test_guest_with_invalid_cookie_falls_back_to_europe_warsaw(): void
    {
        $this->bindRequest(Request::create('/', 'GET', [], ['browser_timezone' => 'Not/A/Timezone']));

        $this->assertSame('Europe/Warsaw', display_timezone());
    }

    public function test_authenticated_user_with_profile_timezone_ignores_cookie(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['timezone' => 'Europe/Berlin']);

        $this->bindRequest(Request::create('/', 'GET', [], ['browser_timezone' => 'America/New_York']));
        $this->actingAs($user);

        $this->assertSame('Europe/Berlin', display_timezone());
    }

    public function test_authenticated_user_without_profile_timezone_uses_cookie(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['timezone' => null]);

        $this->bindRequest(Request::create('/', 'GET', [], ['browser_timezone' => 'America/New_York']));
        $this->actingAs($user);

        $this->assertSame('America/New_York', display_timezone());
    }

    public function test_browser_timezone_from_request_decodes_url_encoded_cookie(): void
    {
        $this->bindRequest(Request::create('/', 'GET', [], ['browser_timezone' => 'America%2FNew_York']));

        $this->assertSame('America/New_York', browser_timezone_from_request());
    }
}
