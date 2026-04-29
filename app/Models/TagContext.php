<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TagContext extends Model
{
    use HasFactory;

    public const CONTEXT_TYPE_ACTIVITY_TYPE = 'activity_type';

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
        if ($this->context_type !== self::CONTEXT_TYPE_ACTIVITY_TYPE) {
            return null;
        }

        return ActivityType::query()->find($this->context_id);
    }
}
