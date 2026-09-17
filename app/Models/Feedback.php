<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'subject',
        'body',
        'email',
        'user_id',
        'status',
        'page_url',
        'locale',
        'user_agent',
        'admin_reply',
        'replied_at',
        'replied_by_id',
        'resolved_at',
        'resolved_by_id',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'type' => FeedbackType::class,
            'status' => FeedbackStatus::class,
            'replied_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function hasReply(): bool
    {
        return $this->replied_at !== null;
    }

    public function isOpen(): bool
    {
        return $this->status === FeedbackStatus::Open;
    }

    public function isResolved(): bool
    {
        return $this->status === FeedbackStatus::Resolved;
    }

    public function reporterEmail(): ?string
    {
        if (filled($this->email)) {
            return $this->email;
        }

        return $this->user?->email;
    }
}
