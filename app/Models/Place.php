<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Place extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'name',
        'parent_id',
        'type',
        'links',
        'desc',
        'is_online',
        'latitude',
        'longitude',
        'logo_path',
        'slug',
        'created_by',
        'updated_by',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
