<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestedBringItemTranslation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'suggested_bring_item_id',
        'locale',
        'label',
    ];

    public function suggestedBringItem(): BelongsTo
    {
        return $this->belongsTo(SuggestedBringItem::class);
    }
}
