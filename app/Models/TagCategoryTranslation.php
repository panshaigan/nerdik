<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagCategoryTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tag_category_id',
        'locale',
        'label',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagCategory::class, 'tag_category_id');
    }
}
