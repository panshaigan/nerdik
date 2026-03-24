<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'category',
        'parent_id',
        'slug',
        'description',
        'logo_path',
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
