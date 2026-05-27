<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'country_id',
        'slug',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CityTranslation::class);
    }

    public function name(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $this->loadMissing('translations');

        $t = $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', 'en')
            ?? $this->translations->first();

        return $t?->name;
    }
}
