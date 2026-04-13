<?php

namespace App\Models;

use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Place extends Model
{
    use HasFactory, HasAutoSlug, HasMetaColumns, SoftDeletes;

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

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
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

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_place');
    }

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
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
