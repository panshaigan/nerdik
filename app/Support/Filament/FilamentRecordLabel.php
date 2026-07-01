<?php

namespace App\Support\Filament;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\City;
use App\Models\Country;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final class FilamentRecordLabel
{
    public static function for(Model $record): string
    {
        return match (true) {
            $record instanceof User => $record->displayName(),
            $record instanceof Tag => $record->displayLabel(),
            $record instanceof TagCategory => $record->name(app()->getLocale()),
            $record instanceof Country => (string) ($record->name() ?? $record->iso_alpha2),
            $record instanceof City => (string) ($record->name() ?? $record->slug),
            $record instanceof ActivityType => (string) $record->slug,
            $record instanceof ActivityProposal => self::activityProposal($record),
            $record instanceof Place => $record->venueRoomLabel(),
            $record instanceof Event => self::datedName($record),
            $record instanceof Activity => self::datedName($record),
            $record instanceof Slot => self::slot($record),
            default => self::fromAttribute($record, 'name')
                ?? self::fromAttribute($record, 'slug')
                ?? self::fromAttribute($record, 'key')
                ?? (string) $record->getKey(),
        };
    }

    public static function activityProposal(ActivityProposal $proposal): string
    {
        $proposal->loadMissing(['activity', 'event']);

        $activity = $proposal->activity?->name ?? '?';
        $event = $proposal->event?->name ?? '?';

        return "#{$proposal->getKey()} — {$activity} @ {$event}";
    }

    public static function slot(Slot $slot): string
    {
        $slot->loadMissing('event');

        $eventName = $slot->event?->name ?? '?';

        return "{$eventName} — {$slot->name}";
    }

    private static function datedName(Event|Activity $record): string
    {
        $label = (string) ($record->name ?? '');
        $startsAt = $record->starts_at;

        if ($startsAt instanceof CarbonInterface) {
            $label .= ' — '.format_in_user_tz($startsAt, 'Y-m-d H:i');
        }

        return $label;
    }

    private static function fromAttribute(Model $record, string $attribute): ?string
    {
        $value = $record->getAttribute($attribute);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
