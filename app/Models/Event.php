<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'name',
        'desc',
        'organization_id',
        'is_public',
        'created_by',
        'updated_by',
        'logo_path',
        'slug',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_public' => 'boolean',
    ];

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

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'event_tag');
    }
}
