<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'category',
        'parent_id',
        'slug',
        'description',
        'logo_path',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function translations()
    {
        return $this->hasMany(TagTranslation::class);
    }

    public function aliases()
    {
        return $this->hasMany(TagAlias::class);
    }

    public function attachedTags()
    {
        return $this->hasMany(AttachedTag::class, 'tag_id');
    }

    public function attachedTo()
    {
        return $this->hasMany(AttachedTag::class, 'attached_tag_id');
    }
}
