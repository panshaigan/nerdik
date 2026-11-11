<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PlayAreaLocationSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPlayArea extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'radius_km',
        'label',
        'city_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_km' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function locationLine(?string $locale = null): ?string
    {
        return PlayAreaLocationSummary::line($this->label, $this->city, $locale);
    }
}
