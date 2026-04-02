<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Slot extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'event_id',
        'created_by',
        'name',
        'starts_at',
        'ends_at',
        'requires_approval',
        'activity_id',
        'max_capacity',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'requires_approval' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Places linked to this slot (typically one row — venue or room).
     */
    public function places()
    {
        return $this->belongsToMany(Place::class, 'slot_place')->withTimestamps();
    }

    /**
     * The primary place for this slot (same row as {@see self::places()}).
     */
    public function place()
    {
        return $this->hasOneThrough(
            Place::class,
            SlotPlace::class,
            'slot_id',
            'id',
            'id',
            'place_id'
        );
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'slot_tag');
    }

    public function activityTypes()
    {
        return $this->hasMany(SlotActivityType::class, 'slot_id');
    }

    /**
     * Expose slot activity types as `$slot->activity_types` even though they are stored
     * in a join table.
     *
     * @return list<string>
     */
    public function getActivityTypesAttribute(): array
    {
        if ($this->relationLoaded('activityTypes')) {
            $loaded = $this->getRelationValue('activityTypes');

            return $loaded
                ? $loaded->pluck('activity_type')->values()->all()
                : [];
        }

        return $this->activityTypes()
            ->pluck('activity_type')
            ->values()
            ->all();
    }

    /**
     * Replace all activity types for this slot.
     *
     * @param  list<string>  $types
     */
    public function setActivityTypes(array $types): void
    {
        $types = array_values(array_unique(array_filter($types, fn ($t) => is_string($t) && $t !== '')));
        $this->activityTypes()->delete();

        if ($types === []) {
            return;
        }

        $rows = array_map(fn ($t) => [
            'slot_id' => $this->id,
            'activity_type' => $t,
        ], $types);

        SlotActivityType::query()->insert($rows);
    }

    /**
     * Base names for mass slot creation (strip trailing " #42" suffix from slot titles).
     *
     * @return list<string>
     */
    public static function baseNameSuggestionsForUser(?int $userId): array
    {
        $raw = static::distinctNameSuggestionsForUser($userId);

        $out = [];
        foreach ($raw as $name) {
            $base = preg_replace('/\s*#\s*\d+\s*$/u', '', $name);
            $base = trim((string) $base);
            if ($base !== '') {
                $out[] = $base;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Distinct slot names from slots created by the user (for suggestion inputs).
     *
     * @return list<string>
     */
    public static function distinctNameSuggestionsForUser(?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        return static::query()
            ->where('created_by', $userId)
            ->whereNotNull('name')
            ->orderBy('created_at', 'desc')
            ->limit(40)
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values()
            ->all();
    }
}
