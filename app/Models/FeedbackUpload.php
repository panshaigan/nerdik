<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FeedbackUploadFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackUpload extends Model
{
    /** @use HasFactory<FeedbackUploadFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'id', 'feedback_id', 'path', 'session_hash', 'ip_hash', 'bytes', 'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['bytes' => 'integer', 'expires_at' => 'datetime'];
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }
}
