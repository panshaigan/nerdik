<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PrunesExpiredSoftDeletes;
use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasAutoSlug, HasFactory, HasMetaColumns, PrunesExpiredSoftDeletes, SoftDeletes;

    protected $table = 'groups';

    #[\Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
        'updated_by',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($user?->is_admin) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $this->users()->whereKey($user->getKey())->exists();
    }

    /** @param Builder<self> $query */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        if ($user?->is_admin) {
            return;
        }

        if ($user === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('users', fn (Builder $members) => $members->whereKey($user->getKey()));
    }

    /** @param Builder<self> $query */
    public function scopeForUser(Builder $query, User $user): void
    {
        if ($user->is_admin) {
            $query->whereHas('users', fn (Builder $members) => $members->whereKey($user->getKey()));

            return;
        }

        $query->visibleTo($user);
    }
}
