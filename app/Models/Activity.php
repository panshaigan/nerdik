<?php

namespace App\Models;

use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
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
        'languages',
        'duration_in_minutes',
        'allows_observers',
        'slug',
        'extra',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'requires_approval' => 'boolean',
        'allows_observers' => 'boolean',
        'is_host_passive' => 'boolean',
        'languages' => 'array',
        'extra' => 'array',
    ];

    public function proposals()
    {
        return $this->hasMany(ActivityProposal::class);
    }

    public function participants()
    {
        return $this->hasMany(ActivityParticipant::class);
    }

    public function waitlist()
    {
        return $this->hasMany(ActivityWaitlistEntry::class)->orderBy('position');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'activity_tag');
    }

    public function slot()
    {
        return $this->hasOne(Slot::class);
    }
}
