<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Slot extends Model
{
    use HasFactory, HasMetaColumns, SoftDeletes;

    private const NAME_SUGGESTIONS_LIMIT = 40;

    protected $fillable = [
        'event_id',
        'created_by',
        'name',
        'starts_at',
        'ends_at',
        'requires_approval',
        'activity_id',
        'place_id',
        'max_capacity',
        'updated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'requires_approval' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function activityTypes(): BelongsToMany
    {
        return $this->belongsToMany(ActivityType::class, 'activity_type_slot');
    }

    /**
     * Expose slot activity types as `$slot->activity_types_ids` even though they are stored
     * in a join table.
     *
     * @return list<int>
     */
    public function getActivityTypesIdsAttribute(): array
    {
        if ($this->relationLoaded('activityTypes')) {
            $loaded = $this->getRelationValue('activityTypes');

            return $loaded
                ? $loaded->pluck('id')->map(fn ($v) => (int) $v)->values()->all()
                : [];
        }

        return $this->activityTypes()
            ->get()
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    /**
     * Replace all activity types for this slot.
     *
     * @param  list<int>  $types
     */
    public function setActivityTypes(array $types): void
    {
        $types = array_values(array_unique(array_filter(array_map('intval', $types), fn ($t) => $t > 0)));
        $this->activityTypes()->sync($types);
    }

    /**
     * Whether this slot allows an activity of the given type.
     * If the slot has no activity-type rows, any type is allowed.
     */
    public function acceptsActivityType(int $activityTypeId): bool
    {
        $allowed = $this->activity_types_ids;
        if ($allowed === []) {
            return true;
        }

        return in_array($activityTypeId, $allowed, true);
    }

    public function acceptsActivity(Activity $activity): bool
    {
        if ($activity->activity_type_id === null) {
            return false;
        }

        return $this->acceptsActivityType((int) $activity->activity_type_id);
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

        $start = $this->starts_at ? format_in_user_tz($this->starts_at, 'd M [H:i') : '—';
        $end = $this->ends_at ? format_in_user_tz($this->ends_at, 'H:i]') : '—';
        $venueRoom = $this->place?->venueRoomLabel() ?? '—';
        $name = (string) $this->name;

        return "{$start} - {$end} {$venueRoom}: {$name}";
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
            ->limit(self::NAME_SUGGESTIONS_LIMIT)
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values()
            ->all();
    }
}
