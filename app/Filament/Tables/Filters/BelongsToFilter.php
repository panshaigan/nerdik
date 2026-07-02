<?php

namespace App\Filament\Tables\Filters;

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
use App\Support\Filament\FilamentRecordLabel;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Str;

final class BelongsToFilter
{
    /** @var array<string, string> */
    private const USER_COLUMN_RELATIONSHIPS = [
        'user_id' => 'user',
        'created_by' => 'creator',
        'updated_by' => 'updater',
        'deleted_by' => 'deleter',
        'cancelled_by' => 'canceller',
    ];

    public static function make(
        string $name,
        ?string $relationship = null,
        string $titleAttribute = 'name',
    ): SelectFilter {
        $relationship ??= Str::camel(Str::beforeLast($name, '_id'));

        return SelectFilter::make($relationship)
            ->relationship($relationship, $titleAttribute);
    }

    public static function user(string $name, ?string $relationship = null): SelectFilter
    {
        $relationship ??= self::USER_COLUMN_RELATIONSHIPS[$name] ?? Str::camel(Str::beforeLast($name, '_id'));

        return SelectFilter::make($relationship)
            ->relationship($relationship, 'nickname')
            ->getOptionLabelFromRecordUsing(fn (User $record): string => FilamentRecordLabel::for($record));
    }

    public static function tag(string $name = 'tag_id', string $relationship = 'tag'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'id')
            ->getOptionLabelFromRecordUsing(fn (Tag $record): string => FilamentRecordLabel::for($record));
    }

    public static function tagCategory(string $name = 'tag_category_id', string $relationship = 'tagCategory'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'key')
            ->getOptionLabelFromRecordUsing(fn (TagCategory $record): string => FilamentRecordLabel::for($record));
    }

    public static function category(string $relationship = 'category'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'key')
            ->getOptionLabelFromRecordUsing(fn (TagCategory $record): string => FilamentRecordLabel::for($record));
    }

    public static function country(string $name = 'country_id', string $relationship = 'country'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'iso_alpha2')
            ->getOptionLabelFromRecordUsing(fn (Country $record): string => FilamentRecordLabel::for($record));
    }

    public static function city(string $name = 'city_id', string $relationship = 'city'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'slug')
            ->getOptionLabelFromRecordUsing(fn (City $record): string => FilamentRecordLabel::for($record));
    }

    public static function activityType(string $name = 'activity_type_id', string $relationship = 'activityType'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'slug')
            ->getOptionLabelFromRecordUsing(fn (ActivityType $record): string => FilamentRecordLabel::for($record));
    }

    public static function activityProposal(string $relationship = 'proposal'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'id')
            ->getOptionLabelFromRecordUsing(fn (ActivityProposal $record): string => FilamentRecordLabel::for($record));
    }

    public static function place(string $name = 'place_id', string $relationship = 'place'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'name')
            ->getOptionLabelFromRecordUsing(fn (Place $record): string => FilamentRecordLabel::for($record));
    }

    public static function event(string $name = 'event_id', string $relationship = 'event'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'name')
            ->getOptionLabelFromRecordUsing(fn (Event $record): string => FilamentRecordLabel::for($record));
    }

    public static function activity(string $name = 'activity_id', string $relationship = 'activity'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'name')
            ->getOptionLabelFromRecordUsing(fn (Activity $record): string => FilamentRecordLabel::for($record));
    }

    public static function slot(string $name = 'slot_id', string $relationship = 'slot'): SelectFilter
    {
        return SelectFilter::make($relationship)
            ->relationship($relationship, 'name')
            ->getOptionLabelFromRecordUsing(fn (Slot $record): string => FilamentRecordLabel::slot($record));
    }
}
