<?php

namespace App\Models;

use App\Traits\HasAutoSlug;
use App\Traits\HasEntityLinks;
use App\Traits\HasMetaColumns;
use Database\Factories\ActivitySeriesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivitySeries extends Model
{
    /** @use HasFactory<ActivitySeriesFactory> */
    use HasAutoSlug, HasEntityLinks, HasFactory, HasMetaColumns, SoftDeletes;

    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'description',
        'slug',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)
            ->orderBy(DB::raw(Activity::scheduleStartsAtSql()))
            ->orderBy('activities.id');
    }

    /**
     * True when at least one non-deleted activity is visible to the given user,
     * or the user can manage the series.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($user?->canModifyEntity($this)) {
            return true;
        }

        return $this->activities()
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Upcoming, non-cancelled sessions visible to the given user.
     *
     * @return Collection<int, Activity>
     */
    public function visibleUpcomingActivities(?User $user): Collection
    {
        $startsAtSql = Activity::scheduleStartsAtSql();

        $query = $this->activities()
            ->whereNull('activities.cancelled_at')
            ->whereRaw("{$startsAtSql} IS NOT NULL")
            ->whereRaw("{$startsAtSql} >= ?", [now()]);

        $query->visibleTo($user);

        return $query
            ->with(['place.city', 'slot.place.city', 'slot.event'])
            ->orderByRaw("{$startsAtSql} ASC")
            ->orderBy('activities.id')
            ->get();
    }
}
