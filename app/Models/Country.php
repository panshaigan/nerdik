<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'iso_alpha2',
    ];

    protected function casts(): array
    {
        return [
            'iso_alpha2' => 'string',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
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
