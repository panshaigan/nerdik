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
        'activity_types',
        'starts_at',
        'ends_at',
        'place_id',
        'requires_approval',
        'activity_id',
        'max_capacity',
        'updated_by',
    ];

    protected $casts = [
        'activity_types' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'requires_approval' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'slot_tag');
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
