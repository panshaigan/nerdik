<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SuggestedBringItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SuggestedBringItem extends Model
{
    /** @use HasFactory<SuggestedBringItemFactory> */
    use HasFactory;

    protected $fillable = [
        'bringable_type',
        'bringable_id',
        'label',
        'sort_order',
        'host_provides',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'host_provides' => 'boolean',
        ];
    }

    public function bringable(): MorphTo
    {
        return $this->morphTo();
    }

    public function activitiesProviding(): BelongsToMany
    {
        return $this->belongsToMany(
            Activity::class,
            'activity_suggested_bring_provisions',
            'suggested_bring_item_id',
            'activity_id',
        );
    }
}
