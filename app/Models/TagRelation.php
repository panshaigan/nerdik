<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagRelation extends Model
{
    protected $fillable = [
        'tag_id',
        'related_tag_id',
    ];

    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }

    public function relatedTag()
    {
        return $this->belongsTo(Tag::class, 'related_tag_id');
    }
}
