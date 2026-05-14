<?php

namespace App\Models;

use App\Enums\ActivityProposalStatus;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityProposal extends Model
{
    use HasFactory, HasMetaColumns, SoftDeletes;

    protected $fillable = [
        'activity_id',
        'event_id',
        'created_by',
        'updated_by',
        'preferred_start_time',
        'status',
        'accepted_slot_id',
    ];

    protected $casts = [
        'status' => ActivityProposalStatus::class,
        'preferred_start_time' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptedSlot(): BelongsTo
    {
        return $this->belongsTo(Slot::class, 'accepted_slot_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ActivityProposalSlot::class);
    }

    /** Slots the proposer targeted (for accept: pick one of these or any free slot in the instance). */
    public function proposedSlots(): BelongsToMany
    {
        return $this->belongsToMany(Slot::class, 'activity_proposal_slot', 'activity_proposal_id', 'slot_id');
    }
}
