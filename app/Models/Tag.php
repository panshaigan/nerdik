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
        'tag_category_id',
        'logo_path',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'category',
    ];

    public function translations()
    {
        return $this->hasMany(TagTranslation::class);
    }

    public function tagCategory()
    {
        return $this->belongsTo(TagCategory::class, 'tag_category_id');
    }

    public function aliases()
    {
        return $this->hasMany(TagAlias::class);
    }

    public function tagRelations()
    {
        return $this->hasMany(TagRelation::class, 'tag_id');
    }

    /** TagRelation rows where this tag is the linked tag (`related_tag_id`, inverse side). */
    public function inverseTagRelations()
    {
        return $this->hasMany(TagRelation::class, 'related_tag_id');
    }

    public function activities()
    {
        return $this->morphedByMany(Activity::class, 'taggable', 'taggables');
    }

    /**
     * Tags eager-loaded for form selectors and browse filters (single query shape app-wide).
     *
     * @return Builder<self>
     */
    public function scopeOrderedForSelector(Builder $query): Builder
    {
        return $query->with(['translations', 'aliases', 'tagRelations', 'tagCategory.translations'])
            ->orderBy('tag_category_id')
            ->orderBy('id');
    }

    public function getCategoryAttribute(): ?string
    {
        if ($this->relationLoaded('tagCategory')) {
            return $this->tagCategory?->key;
        }

        return $this->tagCategory()->value('key');
    }
}
