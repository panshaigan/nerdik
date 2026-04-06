<?php

namespace App\Models;

use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'category',
        'slug',
        'description',
        'logo_path',
        'created_by',
        'updated_by',
    ];

    public function translations()
    {
        return $this->hasMany(TagTranslation::class);
    }

    public function aliases()
    {
        return $this->hasMany(TagAlias::class);
    }

    public function tagAttachments()
    {
        return $this->hasMany(TagAttachment::class, 'tag_id');
    }

    /** TagAttachment rows where this tag is the linked tag (`attached_tag_id`, inverse side). */
    public function inverseTagAttachments()
    {
        return $this->hasMany(TagAttachment::class, 'attached_tag_id');
    }

    public function slots()
    {
        return $this->belongsToMany(Slot::class, 'slot_tag');
    }

    /**
     * Tags eager-loaded for form selectors and browse filters (single query shape app-wide).
     *
     * @return Builder<self>
     */
    public function scopeOrderedForSelector(Builder $query): Builder
    {
        return $query->with(['translations', 'aliases', 'tagAttachments'])
            ->orderBy('category')
            ->orderBy('slug');
    }
}
