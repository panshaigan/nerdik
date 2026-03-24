<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'name',
        'desc',
        'logo_path',
        'slug',
        'created_by',
        'updated_by',
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
