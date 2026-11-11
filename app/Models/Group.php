<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GroupVisibility;
use App\Models\Concerns\PrunesExpiredSoftDeletes;
use App\Support\PlayAreaLocationSummary;
use App\Traits\HasAutoSlug;
use App\Traits\HasMetaColumns;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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
        'visibility',
        'latitude',
        'longitude',
        'radius_km',
        'location_label',
        'city_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => GroupVisibility::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_km' => 'integer',
        ];
    }

    public function isPublic(): bool
    {
        return $this->visibility === GroupVisibility::Public;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'taggables');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null && $this->radius_km !== null;
    }

    public function locationLine(?string $locale = null): ?string
    {
        return PlayAreaLocationSummary::line($this->location_label, $this->city, $locale);
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($user?->is_admin) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($this->users()->whereKey($user->getKey())->exists()) {
            return true;
        }

        return $this->isPublic();
    }

    public function canViewMemberContent(?User $user): bool
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

        $query->where(function (Builder $outer) use ($user): void {
            $outer->where('visibility', GroupVisibility::Public)
                ->orWhereHas('users', fn (Builder $members) => $members->whereKey($user->getKey()));
        });
    }

    /** @param Builder<self> $query */
    public function scopePubliclyDiscoverable(Builder $query): void
    {
        $query->where('visibility', GroupVisibility::Public);
    }

    /** @param Builder<self> $query */
    public function scopeMemberContentVisibleTo(Builder $query, ?User $user): void
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
        $query->whereHas('users', fn (Builder $members) => $members->whereKey($user->getKey()));
    }
}
