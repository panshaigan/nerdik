<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasAutoSlug, HasMetaColumns, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'desc',
        'type',
        'min_participants',
        'max_participants',
        'minimum_age',
        'price',
        'is_host_passive',
        'created_by',
        'updated_by',
        'requires_approval',
        'cancellation_deadline_in_hours',
        'status',
        'logo_path',
        'duration_in_minutes',
        'allows_observers',
        'slug',
        'extra',
    ];

    protected $casts = [
        'type' => ActivityType::class,
        'status' => ActivityStatus::class,
        'price' => 'decimal:2',
        'requires_approval' => 'boolean',
        'allows_observers' => 'boolean',
        'is_host_passive' => 'boolean',
        'extra' => 'array',
    ];

    public function proposals()
    {
        return $this->hasMany(ActivityProposal::class);
    }

    public function participants()
    {
        return $this->hasMany(ActivityUser::class);
    }

    public function waitlist()
    {
        return $this->hasMany(ActivityWaitlistEntry::class)->orderBy('position');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables');
    }

    public function slot()
    {
        return $this->hasOne(Slot::class);
    }

    /**
     * Activities placed on a slot that belongs to an event (excludes proposal-only links until a slot exists).
     *
     * @param  bool  $slotMustNotHaveEnded  When true, only slots whose end (or start if no end) is still in the future.
     */
    public function scopeAttachedToPublicEvent(Builder $query, bool $slotMustNotHaveEnded = false): void
    {
        $query->whereHas('slot', function (Builder $q) use ($slotMustNotHaveEnded) {
            $q->whereNotNull('event_id')
                ->whereHas('event', fn (Builder $e) => $e->where('is_public', true));
            if ($slotMustNotHaveEnded) {
                $q->whereRaw('COALESCE(slots.ends_at, slots.starts_at) >= ?', [now()]);
            }
        });
    }

    /**
     * Headcount that must fit in the physical slot ({@see Slot::$max_capacity}).
     *
     * `max_participants` is the number of players/attendees (the host does not count).
     * If the host is not passive, they occupy one additional seat in the room.
     *
     * When `max_participants` is null, the activity does not define an upper bound for this check.
     *
     * @return int|null Positive integer, or null if capacity cannot be derived from the activity.
     */
    public function physicalHeadcountForSlotCapacity(): ?int
    {
        if ($this->max_participants === null) {
            return null;
        }

        $n = (int) $this->max_participants;
        if (! $this->is_host_passive) {
            $n += 1;
        }

        return $n;
    }
}
