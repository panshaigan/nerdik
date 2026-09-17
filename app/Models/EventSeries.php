<?php

namespace App\Models;

use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Database\Factories\EventSeriesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class EventSeries extends Model
{
    /** @use HasFactory<EventSeriesFactory> */
    use HasAutoSlug, HasFactory, HasMetaColumns, SoftDeletes;

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

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('starts_at')->orderBy('id');
    }

    /**
     * True when at least one non-deleted public event belongs to this series,
     * or the given user created the series.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($user !== null && (int) $user->id === (int) $this->created_by) {
            return true;
        }

        return $this->events()
            ->where('is_public', true)
            ->exists();
    }

    /**
     * Upcoming, non-cancelled editions visible to the given user.
     *
     * @return Collection<int, Event>
     */
    public function visibleUpcomingEvents(?User $user): Collection
    {
        $query = $this->events()
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', now())
            ->whereNull('cancelled_at');

        if ($user === null || (int) $user->id !== (int) $this->created_by) {
            $query->where('is_public', true);
        }

        return $query
            ->with(['places.city'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }
}
