<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagAttachment extends Model
{
    protected $fillable = [
        'tag_id',
        'attached_tag_id',
    ];

    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }

    public function linkedTag()
    {
        return $this->belongsTo(Tag::class, 'attached_tag_id');
    }
}
