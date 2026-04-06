<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagAlias extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tag_id',
        'locale',
        'alias',
    ];

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
