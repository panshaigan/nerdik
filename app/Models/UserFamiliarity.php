<?php

namespace App\Models;

use App\Enums\FamiliarityLevel;
use Database\Factories\UserFamiliarityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserFamiliarity extends Model
{
    /** @use HasFactory<UserFamiliarityFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'level',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => FamiliarityLevel::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
