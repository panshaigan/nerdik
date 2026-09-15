<?php

declare(strict_types=1);

namespace App\Services\Notifications\Scheduled;

use App\Enums\NotificationPreferenceKey;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\Scheduled\ScheduledPeriodicDigestNotification;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;

class ScheduledPeriodicDigestSender
{
    use Localizable;

    public function __construct(
        private readonly ScheduledNotificationCollector $collector,
    ) {}

    public function sendDueDigests(): int
    {
        $sendTime = (string) config('scheduled_notifications.daily_send_time', '09:00');
        $now = CarbonImmutable::now('UTC');
        $matchingTimezones = $this->timezonesMatchingSendTime($now, $sendTime);

        if ($matchingTimezones === []) {
            return 0;
        }

        $sent = 0;
        $fallback = $this->fallbackTimezoneName();
        $includesFallback = in_array($fallback, $matchingTimezones, true);
        $namedMatches = array_values(array_filter(
            $matchingTimezones,
            fn (string $timezone): bool => $timezone !== $fallback
        ));

        User::query()
            ->select(['users.id', 'users.email', 'users.locale'])
            ->with('profile:id,user_id,timezone,notification_preferences')
            ->where(function (Builder $query) use ($namedMatches, $includesFallback, $fallback): void {
                if ($namedMatches !== []) {
                    $query->whereHas(
                        'profile',
                        fn (Builder $profileQuery) => $profileQuery->whereIn('timezone', $namedMatches)
                    );
                }

                if ($includesFallback) {
                    $method = $namedMatches !== [] ? 'orWhere' : 'where';
                    $query->{$method}(function (Builder $fallbackQuery) use ($fallback): void {
                        $fallbackQuery
                            ->whereDoesntHave('profile')
                            ->orWhereHas('profile', function (Builder $profileQuery) use ($fallback): void {
                                $profileQuery
                                    ->whereNull('timezone')
                                    ->orWhere('timezone', '')
                                    ->orWhere('timezone', $fallback);
                            });
                    });
                }
            })
            ->orderBy('users.id')
            ->cursor()
            ->each(function (User $user) use ($now, $sendTime, &$sent): void {
                if (! $this->userLocalTimeMatches($user, $now, $sendTime)) {
                    return;
                }

                if (! $this->userWantsAnyScheduledDigest($user)) {
                    return;
                }

                $dispatchDate = $now->setTimezone($this->timezoneForUser((string) ($user->profile?->timezone ?? '')))->toDateString();
                $items = $this->withLocale($user->preferredLocale(), function () use ($user, $now): array {
                    return collect($this->collector->collectForUser($user, $now))
                        ->filter(fn (array $item): bool => $user->retainsScheduledDigestItem($item))
                        ->values()
                        ->all();
                });

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

                $sent++;
            });

        return $sent;
    }

    /**
     * @return list<string>
     */
    private function timezonesMatchingSendTime(CarbonImmutable $nowUtc, string $sendTime): array
    {
        $fallback = $this->fallbackTimezoneName();
        $candidates = UserProfile::query()
            ->select('timezone')
            ->distinct()
            ->pluck('timezone')
            ->map(function (mixed $timezone) use ($fallback): string {
                $name = is_string($timezone) ? trim($timezone) : '';

                return $name !== '' ? $name : $fallback;
            })
            ->push($fallback)
            ->unique()
            ->values()
            ->all();

        return array_values(array_filter(
            $candidates,
            function (string $timezoneName) use ($nowUtc, $sendTime): bool {
                try {
                    $timezone = new DateTimeZone($timezoneName);
                } catch (\Throwable) {
                    $timezone = new DateTimeZone($this->fallbackTimezoneName());
                }

                return $nowUtc->setTimezone($timezone)->format('H:i') === $sendTime;
            }
        ));
    }

    private function userLocalTimeMatches(User $user, CarbonImmutable $nowUtc, string $sendTime): bool
    {
        $timezone = $this->timezoneForUser((string) ($user->profile?->timezone ?? ''));

        return $nowUtc->setTimezone($timezone)->format('H:i') === $sendTime;
    }

    private function userWantsAnyScheduledDigest(User $user): bool
    {
        foreach (NotificationPreferenceKey::cases() as $key) {
            if (! str_starts_with($key->value, 'scheduled_')) {
                continue;
            }

            if ($user->wantsNotificationChannel($key, 'in_app') || $user->wantsNotificationChannel($key, 'email')) {
                return true;
            }
        }

        return false;
    }

    private function timezoneForUser(string $timezoneName): DateTimeZone
    {
        $fallback = $this->fallbackTimezoneName();
        $candidate = $timezoneName !== '' ? $timezoneName : $fallback;

        try {
            return new DateTimeZone($candidate);
        } catch (\Throwable) {
            return new DateTimeZone($fallback);
        }
    }

    private function fallbackTimezoneName(): string
    {
        return (string) config('scheduled_notifications.timezone_fallback', config('app.timezone', 'UTC'));
    }
}
