<?php

namespace App\Models;

use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Place extends Model
{
    use HasAutoSlug, HasMetaColumns, SoftDeletes;

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
        'desc',
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

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_place')->withTimestamps();
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
}
