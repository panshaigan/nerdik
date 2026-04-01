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
        'age_limit',
        'price',
        'host_user_id',
        'passive_host',
        'created_by',
        'updated_by',
        'is_restricted',
        'signoff_deadline_hours',
        'status',
        'logo_path',
        'languages',
        'duration_minutes',
        'open_for_observers',
        'slug',
        'extra',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_restricted' => 'boolean',
        'open_for_observers' => 'boolean',
        'passive_host' => 'boolean',
        'languages' => 'array',
        'extra' => 'array',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

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
