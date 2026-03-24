<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Sets created_by, updated_by from the authenticated user.
 * When using with SoftDeletes, sets deleted_by on soft delete.
 * All datetime columns are stored in UTC (config app.timezone).
 */
trait HasMetaColumns
{
    public static function bootHasMetaColumns(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && Schema::hasColumn($model->getTable(), 'created_by') && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check() && Schema::hasColumn($model->getTable(), 'updated_by')) {
                $model->updated_by = auth()->id();
            }
            // When SoftDeletes runs delete(), it does an update with deleted_at set
            if (auth()->check() && Schema::hasColumn($model->getTable(), 'deleted_by') && $model->isDirty('deleted_at')) {
                $model->deleted_by = auth()->id();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
