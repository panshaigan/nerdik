<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'desc',
        'logo_path',
        'slug',
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
