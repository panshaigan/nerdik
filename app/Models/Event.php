<?php

namespace App\Models;

use App\Enums\EventLogoSource;
use App\Models\Concerns\InteractsWithUploadedLogo;
use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Event extends Model implements HasMedia
{
    use HasAutoSlug, HasFactory, HasMetaColumns, InteractsWithUploadedLogo, SoftDeletes;

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
        'logo_source',
        'listing_media_id',
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
        'logo_source' => EventLogoSource::class,
    ];

    public function listingMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'listing_media_id');
    }

    /**
     * @return array<string|int, mixed>
     */
    public static function listingCardEagerLoad(): array
    {
        return [
            'listingMedia',
            'creator',
            'organization',
            'media' => fn ($query) => $query->where('collection_name', 'logo'),
            'places.country.translations',
            'places.city.translations',
            'slots.activity.activityType',
            'slots.activityTypes',
        ];
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * True when any event slot is bound to an activity.
     */
    public function hasScheduledSlotActivities(): bool
    {
        return $this->slots()->whereNotNull('activity_id')->exists();
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

    /**
     * When the event is not cancelled: hard delete must not be used — cancel first —
     * if anything is scheduled on slots or there is signup/waitlist pressure.
     */
    public function organiserHardDeleteBlockedWhileActive(): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        return $this->hasScheduledSlotActivities() || $this->hasSignupPressure();
    }

    /** Use explicit cancel when deleting would wrongly skip notifying stakeholders about a non-empty programme. */
    public function qualifiesForExplicitOrganiserCancellation(): bool
    {
        return $this->hasScheduledSlotActivities() || $this->hasSignupPressure();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function interestedUsers(): MorphToMany
    {
        return $this->morphToMany(User::class, 'interest', 'user_interests');
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'event_place');
    }

    public function enrollmentWindows(): HasMany
    {
        return $this->hasMany(EventEnrollmentWindow::class)->orderBy('starts_at');
    }

    public function eventEnrollmentWindows(): HasMany
    {
        return $this->hasMany(EventEnrollmentWindow::class)->orderBy('starts_at');
    }

    /**
     * Human-friendly owner/host label:
     * - if an organization is attached, show organization name
     * - otherwise show the creator user's canonical display name (nickname)
     */
    public function hostDisplayName(): string
    {
        if ($this->organization) {
            return $this->organization->name;
        }

        if ($this->creator) {
            return $this->creator->displayName();
        }

        return '';
    }

    public function compactDateAttribute(): string
    {
        return format_date_range_compact($this->starts_at, $this->ends_at);
    }

    public function compactPlaceSummary(): string
    {
        $this->loadMissing('places.city');

        $venues = $this->places
            ->filter(fn ($place) => $place !== null && $place->type === Place::TYPE_VENUE && filled($place->name))
            ->unique('id')
            ->values();

        if ($venues->isEmpty()) {
            return '';
        }

        $venueNames = $venues
            ->map(fn ($place) => trim((string) $place->name))
            ->filter()
            ->values();

        $cityNames = $venues
            ->map(fn ($place) => $place->city?->name(app()->getLocale()))
            ->filter()
            ->unique()
            ->values();

        if ($cityNames->count() === 1 && $venueNames->count() > 1) {
            $cityName = (string) $cityNames->first();

            return $cityName !== ''
                ? sprintf('%s (%s)', $venueNames->implode(', '), $cityName)
                : $venueNames->implode(', ');
        }

        return $venues
            ->map(function ($place): string {
                $venueName = trim((string) $place->name);
                $cityName = trim((string) ($place->city?->name(app()->getLocale()) ?? ''));

                if ($cityName === '') {
                    return $venueName;
                }

                return sprintf('%s (%s)', $venueName, $cityName);
            })
            ->filter()
            ->implode(', ');
    }
}
