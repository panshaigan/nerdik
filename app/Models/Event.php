<?php

namespace App\Models;

use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasAutoSlug, HasFactory, HasMetaColumns, SoftDeletes;

    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'description',
        'organization_id',
        'is_public',
        'created_by',
        'updated_by',
        'logo_path',
        'slug',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_public' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * True when any slot activity has roster pressure (participants or waitlist).
     * Used to force cancel instead of hard delete for organisers.
     */
    public function hasSignupPressure(): bool
    {
        $activityIds = $this->slots()
            ->whereNotNull('activity_id')
            ->pluck('activity_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($activityIds === []) {
            return false;
        }

        $hasParticipants = ActivityUser::query()
            ->whereIn('activity_id', $activityIds)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasParticipants) {
            return true;
        }

        return ActivityWaitlistEntry::query()
            ->whereIn('activity_id', $activityIds)
            ->exists();
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    public function proposals()
    {
        return $this->hasMany(ActivityProposal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interestedUsers()
    {
        return $this->belongsToMany(User::class, 'user_event_interests');
    }

    public function places()
    {
        return $this->belongsToMany(Place::class, 'event_place');
    }

    public function enrollmentWindows()
    {
        return $this->hasMany(EventEnrollmentWindow::class)->orderBy('starts_at');
    }

    public function eventEnrollmentWindows()
    {
        return $this->hasMany(EventEnrollmentWindow::class)->orderBy('starts_at');
    }

    /**
     * Human-friendly owner/host label:
     * - if an organization is attached, show organization name
     * - otherwise show the creator user nickname/email
     */
    public function hostDisplayName(): string
    {
        if ($this->organization) {
            return $this->organization->name;
        }

        if ($this->creator) {
            return $this->creator->nickname ?? $this->creator->email;
        }

        return '';
    }

    public function compactDateAttribute(): string
    {
        return format_date_range_compact($this->starts_at, $this->ends_at);
    }
}
