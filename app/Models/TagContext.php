<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TagContext extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tag_id',
        'context_type',
        'context_id',
    ];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function activityType(): ?ActivityType
    {
        if ($this->context_type !== 'activity_type') {
            return null;
        }

        return ActivityType::query()->find($this->context_id);
    }
}
