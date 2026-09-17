<?php

namespace App\Models;

use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Place extends Model
{
    use HasAutoSlug, HasFactory, HasMetaColumns, SoftDeletes;

    public const TYPE_ROOM = 'room';

    public const TYPE_VENUE = 'venue';

    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'address',
        'city_id',
        'country_id',
        'parent_id',
        'type',
        'links',
        'description',
        'is_online',
        'latitude',
        'longitude',
        'logo_path',
        'slug',
        'created_by',
        'updated_by',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Room-level places are tied to a parent venue; maps should show venues (or other types), not duplicate room pins.
     *
     * @param  Builder<Place>  $query
     * @return Builder<Place>
     */
    public function scopeWithoutRooms($query)
    {
        return $query->where('type', '!=', 'room');
    }

    /**
     * Physical venues (event maps and event–place links use this type only).
     * Use {@see Country} and {@see City} for country/city data — not place types.
     *
     * @param  Builder<Place>  $query
     * @return Builder<Place>
     */
    public function scopeVenues($query)
    {
        return $query->where('type', 'venue');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_place');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Localized "City, Country" for UI lists.
     */
    public function locationLabel(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $parts = array_filter([
            $this->city?->name($locale),
            $this->country?->name($locale),
        ]);

        return implode(', ', $parts);
    }

    /**
     * Venue name for compact UIs: parent venue when this place is a room; otherwise this place name.
     */
    public function venueName(): string
    {
        $this->loadMissing('parent');
        if ($this->parent_id && $this->parent) {
            return (string) $this->parent->name;
        }

        return (string) $this->name;
    }

    /**
     * Compact venue label for listing cards and event headers: "Venue (City)".
     */
    public function compactVenueSummary(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $this->loadMissing(['city', 'parent']);

        $venueName = trim($this->venueName());
        if ($venueName === '') {
            return '';
        }

        $cityName = trim((string) ($this->city?->name($locale) ?? ''));
        if ($cityName === '') {
            return $venueName;
        }

        return sprintf('%s (%s)', $venueName, $cityName);
    }

    /**
     * Calendar LOCATION text for the venue (never the room): "Venue, Address, City".
     */
    public function calendarLocationLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $this->loadMissing(['city', 'parent']);

        $venue = $this->parent_id && $this->parent ? $this->parent : $this;
        $venue->loadMissing('city');

        $name = trim((string) $venue->name);
        $address = trim((string) ($venue->address ?? ''));
        $cityName = trim((string) ($venue->city?->name($locale) ?? ''));

        $parts = [];
        if ($name !== '') {
            $parts[] = $name;
        }
        if ($address !== '') {
            $parts[] = $address;
        }
        if ($cityName !== '' && ($address === '' || ! str_contains(mb_strtolower($address), mb_strtolower($cityName)))) {
            $parts[] = $cityName;
        }

        return implode(', ', $parts);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Coordinates for the physical venue (parent when this place is a room).
     *
     * @return array{0: float, 1: float}|null
     */
    public function venueCoordinates(): ?array
    {
        $this->loadMissing('parent');
        $venue = $this->parent_id && $this->parent ? $this->parent : $this;

        if (! $venue->hasCoordinates()) {
            return null;
        }

        return [(float) $venue->latitude, (float) $venue->longitude];
    }

    /**
     * For slot UIs: "Venue · Room" when this place is a room under a venue; otherwise the place name.
     */
    public function venueRoomLabel(): string
    {
        $this->loadMissing('parent');
        if ($this->parent_id && $this->parent) {
            return $this->parent->name.' · '.$this->name;
        }

        return (string) $this->name;
    }
}
