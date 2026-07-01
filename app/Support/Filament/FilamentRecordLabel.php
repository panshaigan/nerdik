<?php

namespace App\Support\Filament;

use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\City;
use App\Models\Country;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
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

    private static function fromAttribute(Model $record, string $attribute): ?string
    {
        $value = $record->getAttribute($attribute);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
