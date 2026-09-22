<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EntityLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityLink extends Model
{
    /** @use HasFactory<EntityLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
