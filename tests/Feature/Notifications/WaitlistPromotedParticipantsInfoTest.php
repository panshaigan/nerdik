<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WaitlistPromotedParticipantsInfoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function mail_includes_participants_only_info_when_present(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'participants_only_description' => '<p>Bring your character sheet</p>',
        ]);

        $mail = (new WaitlistPromotedNotification($activity))->toMail($user);

        $this->assertStringContainsString(
            __('ui.notifications.waitlist_promoted_participants_info_heading'),
            implode("\n", $mail->introLines)
        );
        $this->assertStringContainsString('Bring your character sheet', implode("\n", $mail->introLines));
        $this->assertStringContainsString(
            __('ui.notifications.waitlist_promoted_participants_info_on_page'),
            implode("\n", $mail->introLines)
        );
        $this->assertStringContainsString('tab=info', (string) $mail->actionUrl);
    }

    #[Test]
    public function mail_omits_participants_only_block_when_empty(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'participants_only_description' => null,
        ]);

        $mail = (new WaitlistPromotedNotification($activity))->toMail($user);
        $lines = implode("\n", $mail->introLines);

        $this->assertStringNotContainsString(
            __('ui.notifications.waitlist_promoted_participants_info_heading'),
            $lines
        );
    }
}
