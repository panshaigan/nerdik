<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagCategory extends Model
{
    protected $fillable = [
        'key',
    ];

    public function translations()
    {
        return $this->hasMany(TagCategoryTranslation::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }

    public function name(string $locale): string
    {
        $current = $this->translations->firstWhere('locale', $locale)?->label;
        if (is_string($current) && trim($current) !== '') {
            return $current;
        }

        $en = $this->translations->firstWhere('locale', 'en')?->label;
        if (is_string($en) && trim($en) !== '') {
            return $en;
        }

        return (string) $this->key;
    }
}
