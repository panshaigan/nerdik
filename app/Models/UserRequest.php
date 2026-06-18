<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use Database\Factories\UserRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserRequest extends Model
{
    /** @use HasFactory<UserRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
        'requester_id',
        'recipient_id',
        'subject_type',
        'subject_id',
        'message',
        'responded_at',
        'responded_by_id',
        'expires_at',
        'resolution_outcome',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UserRequestType::class,
            'status' => UserRequestStatus::class,
            'resolution_outcome' => UserRequestResolutionOutcome::class,
            'responded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', UserRequestStatus::Pending);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpiredBefore(Builder $query, \DateTimeInterface $moment): Builder
    {
        return $query
            ->pending()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $moment);
    }

    public function isPending(): bool
    {
        return $this->status === UserRequestStatus::Pending;
    }

    public function isExpiredByTime(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
