<?php

namespace App\Models;

use App\Enums\LotteryDrawTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLotteryDraw extends Model
{
    protected $fillable = [
        'activity_id',
        'trigger',
        'enrollment_window_id',
        'drawn_at',
    ];

    protected $casts = [
        'trigger' => LotteryDrawTrigger::class,
        'drawn_at' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function enrollmentWindow(): BelongsTo
    {
        return $this->belongsTo(EventEnrollmentWindow::class, 'enrollment_window_id');
    }
}
