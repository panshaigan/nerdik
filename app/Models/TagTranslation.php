<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TagTranslation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tag_id',
        'locale',
        'label',
        'slug',
    ];

    protected static function booted(): void
    {
        static::saving(function (TagTranslation $translation) {
            $label = trim((string) ($translation->label ?? ''));
            $locale = trim((string) ($translation->locale ?? ''));

            if ($locale === '') {
                return;
            }

            if (! $translation->isDirty('label') && filled($translation->slug)) {
                return;
            }

            $base = Str::slug($label);
            $base = $base !== '' ? $base : 'tag';
            $translation->slug = static::uniqueSlugForLocale($locale, $base, $translation->id);
        });
    }

    private static function uniqueSlugForLocale(string $locale, string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $i = 1;

        while (static::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $i++;
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
