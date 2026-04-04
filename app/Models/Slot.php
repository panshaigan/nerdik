<?php

namespace App\Models;

use App\Enums\ActivityType;
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
        return $this->belongsToMany(Place::class, 'place_slot')->withTimestamps();
    }

    /**
     * The primary place for this slot (same row as {@see self::places()}).
     */
    public function place()
    {
        return $this->hasOneThrough(
            Place::class,
            PlaceSlot::class,
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
        return $this->hasMany(ActivityTypeSlot::class, 'slot_id');
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
                ? $loaded->pluck('activity_type')->map(fn ($v) => $this->canonicalActivityTypeValue($v))->values()->all()
                : [];
        }

        // Use hydrated models so `activity_type` goes through casts (avoids raw DB vs enum mismatch).
        return $this->activityTypes()
            ->get()
            ->pluck('activity_type')
            ->map(fn ($v) => $this->canonicalActivityTypeValue($v))
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

        ActivityTypeSlot::query()->insert($rows);
    }

    /**
     * Canonical string for {@see ActivityType} (trim, case-fold, map via backed enum when possible).
     */
    private function canonicalActivityTypeValue(ActivityType|string $value): string
    {
        if ($value instanceof ActivityType) {
            return $value->value;
        }

        $s = mb_strtolower(trim((string) $value));

        return ActivityType::tryFrom($s)?->value ?? $s;
    }

    /**
     * Whether this slot allows an activity of the given type.
     * If the slot has no activity-type rows, any type is allowed.
     */
    public function acceptsActivityType(ActivityType|string $type): bool
    {
        $value = $this->canonicalActivityTypeValue($type);
        $allowed = $this->activity_types;
        if ($allowed === []) {
            return true;
        }

        return in_array($value, $allowed, true);
    }

    public function acceptsActivity(Activity $activity): bool
    {
        return $this->acceptsActivityType($activity->type);
    }

    /**
     * Whether the slot’s start→end window is at least as long as the activity’s duration.
     * If the activity has no positive duration, this does not restrict selection.
     * If the slot has no start/end time, duration is not used to filter — any activity fits.
     */
    public function fitsActivityDuration(Activity $activity): bool
    {
        $minutes = (int) ($activity->duration_in_minutes ?? 0);
        if ($minutes <= 0) {
            return true;
        }

        if ($this->starts_at === null || $this->ends_at === null) {
            return true;
        }

        if ($this->ends_at->lte($this->starts_at)) {
            return false;
        }

        return $this->starts_at->diffInMinutes($this->ends_at) >= $minutes;
    }

    /**
     * Whether the slot’s {@see self::$max_capacity} can fit the activity’s physical headcount
     * ({@see Activity::physicalHeadcountForSlotCapacity()}). If either side omits a limit, this does not block.
     */
    public function fitsActivityCapacity(Activity $activity): bool
    {
        if ($this->max_capacity === null) {
            return true;
        }

        $needed = $activity->physicalHeadcountForSlotCapacity();
        if ($needed === null) {
            return true;
        }

        return $needed <= (int) $this->max_capacity;
    }

    /**
     * Activity type, duration, and room capacity match this empty slot for proposal acceptance.
     */
    public function fitsProposalActivity(Activity $activity): bool
    {
        return $this->acceptsActivity($activity)
            && $this->fitsActivityDuration($activity)
            && $this->fitsActivityCapacity($activity);
    }

    /**
     * Single-line label for organizer “accept proposal” slot dropdowns.
     */
    public function proposalAcceptOptionLabel(): string
    {
        $this->loadMissing('place.parent');

        $start = $this->starts_at ? format_in_user_tz($this->starts_at, 'H:i') : '—';
        $end = $this->ends_at ? format_in_user_tz($this->ends_at, 'H:i') : '—';
        $venueRoom = $this->place?->venueRoomLabel() ?? '—';
        $name = (string) $this->name;
        $cap = $this->max_capacity !== null ? (string) $this->max_capacity : '—';

        return "{$start} - {$end} {$venueRoom}: {$name} {$cap}";
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
