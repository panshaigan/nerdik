<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'name',
        'desc',
        'organization_id',
        'is_public',
        'created_by',
        'logo_path',
        'slug',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function instances()
    {
        return $this->hasMany(EventInstance::class);
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
