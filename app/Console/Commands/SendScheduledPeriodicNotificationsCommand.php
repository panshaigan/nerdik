<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\Scheduled\ScheduledPeriodicDigestNotification;
use App\Services\Notifications\Scheduled\ScheduledNotificationCollector;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('notifications:scheduled-digest')]
#[Description('Send scheduled periodic digest notifications')]
class SendScheduledPeriodicNotificationsCommand extends Command
{
    public function __construct(
        private readonly ScheduledNotificationCollector $collector
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sendTime = (string) config('scheduled_notifications.daily_send_time', '09:00');
        $now = CarbonImmutable::now('UTC');

        User::query()
            ->select(['id', 'timezone', 'email', 'notification_preferences'])
            ->orderBy('id')
            ->cursor()
            ->each(function (User $user) use ($now, $sendTime): void {
                $timezone = $this->timezoneForUser((string) $user->timezone);
                $localNow = $now->setTimezone($timezone);
                if ($localNow->format('H:i') !== $sendTime) {
                    return;
                }

                $dispatchDate = $localNow->toDateString();
                $items = collect($this->collector->collectForUser($user, $now))
                    ->filter(fn (array $item): bool => $user->retainsScheduledDigestItem($item))
                    ->values()
                    ->all();
                if ($items === []) {
                    return;
                }

                $alreadySentKeys = DB::table('scheduled_notification_dispatches')
                    ->where('user_id', $user->id)
                    ->whereDate('dispatch_date', $dispatchDate)
                    ->whereIn('dedupe_key', collect($items)->pluck('dedupe_key')->all())
                    ->pluck('dedupe_key')
                    ->all();

                $newItems = collect($items)
                    ->reject(fn (array $item): bool => in_array($item['dedupe_key'], $alreadySentKeys, true))
                    ->values()
                    ->all();

                if ($newItems === []) {
                    return;
                }

                $user->notify(new ScheduledPeriodicDigestNotification($newItems, $dispatchDate));

                DB::table('scheduled_notification_dispatches')->insert(
                    collect($newItems)
                        ->map(fn (array $item): array => [
                            'user_id' => $user->id,
                            'dispatch_date' => $dispatchDate,
                            'dedupe_key' => $item['dedupe_key'],
                            'sent_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all()
                );
            });

        return self::SUCCESS;
    }

    private function timezoneForUser(string $timezoneName): DateTimeZone
    {
        $fallback = (string) config('scheduled_notifications.timezone_fallback', config('app.timezone', 'UTC'));
        $candidate = $timezoneName !== '' ? $timezoneName : $fallback;

        try {
            return new DateTimeZone($candidate);
        } catch (\Throwable) {
            return new DateTimeZone($fallback);
        }
    }
}
