<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerifyPendingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_email_can_be_verified_with_valid_signed_link(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => 'new@example.com',
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'profile.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('new@example.com')]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);

        $response
            ->assertRedirect(route('profile', ['tab' => 'advanced']))
            ->assertSessionHas('ui.toast.type', 'success');
    }

    public function test_pending_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => 'new@example.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'profile.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong@example.com')]
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertForbidden();

        $user->refresh();

        $this->assertSame('current@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);
    }

    public function test_pending_email_verification_is_forbidden_without_pending_email(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'pending_email' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'profile.email.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('current@example.com')]
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertForbidden();
    }
}
