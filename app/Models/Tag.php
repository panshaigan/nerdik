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

    public function tagRelations()
    {
        return $this->hasMany(TagRelation::class, 'tag_id');
    }

    /** TagRelation rows where this tag is the linked tag (`related_tag_id`, inverse side). */
    public function inverseTagRelations()
    {
        return $this->hasMany(TagRelation::class, 'related_tag_id');
    }

    /**
     * Tags eager-loaded for form selectors and browse filters (single query shape app-wide).
     *
     * @return Builder<self>
     */
    public function scopeOrderedForSelector(Builder $query): Builder
    {
        return $query->with(['translations', 'aliases', 'tagRelations'])
            ->orderBy('category')
            ->orderBy('id');
    }
}
