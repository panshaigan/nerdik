<?php

namespace Tests\Feature\Notifications;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailNotificationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_notification_creates_email_log_entry(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $user->notify(new WaitlistPromotedNotification($activity));

        $row = DB::table('notification_email_logs')->first();

        $this->assertNotNull($row);
        $this->assertSame(WaitlistPromotedNotification::class, $row->notification_type);
        $this->assertSame((int) $user->id, (int) $row->recipient_user_id);
        $this->assertSame($user->email, $row->recipient_email);
        $this->assertNotNull($row->sent_at);
    }
}
