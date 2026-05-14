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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasAutoSlug, HasFactory, HasMetaColumns, SoftDeletes;

    public const HOSTING_MODE_DRAFT = 1;

    public const HOSTING_MODE_SELF_HOSTED = 2;

    public const HOSTING_MODE_PROPOSED_TO_EVENT = 3;

    public const HOSTING_MODE_SCHEDULED_ON_EVENT = 4;

    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'description',
        'activity_type_id',
        'hosting_mode',
        'place_id',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'cancelled_with_event_id',
        'min_participants',
        'max_participants',
        'minimum_age',
        'price',
        'is_host_passive',
        'created_by',
        'updated_by',
        'requires_approval',
        'cancellation_deadline_in_hours',
        'logo_path',
        'duration_in_minutes',
        'allows_observers',
        'slug',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'hosting_mode' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'requires_approval' => 'boolean',
        'allows_observers' => 'boolean',
        'is_host_passive' => 'boolean',
    ];

    public function proposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActivityUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_user');
    }

    public function interestedUsers(): MorphToMany
    {
        return $this->morphToMany(User::class, 'interest', 'user_interests');
    }

    public function waitlist(): HasMany
    {
        return $this->hasMany(ActivityWaitlistEntry::class)->orderBy('position');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables');
    }

    public function slot(): HasOne
    {
        return $this->hasOne(Slot::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cancelledWithEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'cancelled_with_event_id');
    }

    /** @return list<int> */
    public static function hostingModes(): array
    {
        return [
            self::HOSTING_MODE_DRAFT,
            self::HOSTING_MODE_SELF_HOSTED,
            self::HOSTING_MODE_PROPOSED_TO_EVENT,
            self::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isJoinableMode(): bool
    {
        return in_array((int) $this->hosting_mode, [
            self::HOSTING_MODE_SELF_HOSTED,
            self::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ], true);
    }

    /**
     * Hard delete is allowed only for draft-ish activities without roster and not scheduled on an event.
     */
    public function allowsHardDeletion(): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        if ((int) $this->hosting_mode === self::HOSTING_MODE_SCHEDULED_ON_EVENT) {
            return false;
        }

        if ($this->participants()->whereNull('deleted_at')->exists()) {
            return false;
        }

        return ! $this->waitlist()->exists();
    }

    /**
     * Activities visible in browse:
     * - self-hosted activities
     * - activities scheduled on a public event slot.
     */
    public function scopeAttachedToPublicEvent(Builder $query, bool $slotMustNotHaveEnded = false): void
    {
        $query->whereNull('activities.cancelled_at')
            ->where(function (Builder $outer) use ($slotMustNotHaveEnded): void {
                $outer
                    ->where(function (Builder $selfHosted) use ($slotMustNotHaveEnded): void {
                        $selfHosted->where('activities.hosting_mode', self::HOSTING_MODE_SELF_HOSTED);
                        if ($slotMustNotHaveEnded) {
                            $selfHosted->whereRaw('COALESCE(activities.ends_at, activities.starts_at) >= ?', [now()]);
                        }
                    })
                    ->orWhere(function (Builder $scheduled) use ($slotMustNotHaveEnded): void {
                        $scheduled
                            ->where('activities.hosting_mode', self::HOSTING_MODE_SCHEDULED_ON_EVENT)
                            ->whereHas('slot', function (Builder $q) use ($slotMustNotHaveEnded): void {
                                $q->whereNotNull('event_id')
                                    ->whereHas('event', fn (Builder $e) => $e
                                        ->where('is_public', true)
                                        ->whereNull('cancelled_at'));
                                if ($slotMustNotHaveEnded) {
                                    $q->whereRaw('COALESCE(slots.ends_at, slots.starts_at) >= ?', [now()]);
                                }
                            });
                    });
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

    public function getDurationForHumansAttribute(): string
    {
        $hours = intdiv($this->duration_in_minutes, 60);
        $minutes = $this->duration_in_minutes % 60;

        return match (true) {
            $hours > 0 && $minutes > 0 => "{$hours}h {$minutes}min",
            $hours > 0 => "{$hours}h",
            default => "{$minutes}min",
        };
    }
}
