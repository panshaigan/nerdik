<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagCategoryTranslation extends Model
{
    protected $fillable = [
        'tag_category_id',
        'locale',
        'label',
    ];

    public function category()
    {
        return $this->belongsTo(TagCategory::class, 'tag_category_id');
    }
}
