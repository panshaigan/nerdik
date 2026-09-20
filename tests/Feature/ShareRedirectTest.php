<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\Sharing\ShareLinks;
use App\Support\Sharing\ShareTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShareRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_redirect_sends_guest_to_platform_intent(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Redirect Share Event',
        ]);

        $shareLinks = app(ShareLinks::class);
        $payload = $shareLinks->forEvent($event);
        $this->assertNotNull($payload);

        $menuUrl = $shareLinks->menuUrl($payload, ShareTarget::Facebook);
        $this->assertNotNull($menuUrl);

        $expectedIntent = $shareLinks->intentUrl($payload, ShareTarget::Facebook);
        $this->assertNotNull($expectedIntent);

        $this->get($menuUrl)
            ->assertRedirect($expectedIntent);
    }

    public function test_share_redirect_rejects_external_destination_urls(): void
    {
        $this->get(route('share.redirect', [
            'target' => 'facebook',
            'url' => 'https://evil.example/phish',
            'title' => 'Nope',
            'text' => 'Nope',
            'campaign' => 'event',
        ]))->assertNotFound();
    }

    public function test_share_redirect_rejects_unknown_targets(): void
    {
        $this->get(route('share.redirect', [
            'target' => 'myspace',
            'url' => config('app.url').'/events/demo',
            'title' => 'Nope',
            'campaign' => 'event',
        ]))->assertNotFound();
    }
}
