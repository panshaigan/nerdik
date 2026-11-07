<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAvailabilityWeekday extends Model
{
    protected $fillable = [
        'user_id',
        'mon',
        'tue',
        'wed',
        'thu',
        'fri',
        'sat',
        'sun',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mon' => 'boolean',
            'tue' => 'boolean',
            'wed' => 'boolean',
            'thu' => 'boolean',
            'fri' => 'boolean',
            'sat' => 'boolean',
            'sun' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAvailableOnIsoWeekday(int $isoWeekday): ?bool
    {
        $column = match ($isoWeekday) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        return (bool) $this->{$column};
    }
}
