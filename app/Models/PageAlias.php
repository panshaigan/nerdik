<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageAliasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageAlias extends Model
{
    /** @use HasFactory<PageAliasFactory> */
    use HasFactory;

    protected $fillable = [
        'alias',
        'target_path',
        'created_by_id',
        'hits',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hits' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
