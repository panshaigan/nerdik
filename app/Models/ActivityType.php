<?php

namespace App\Models;

use App\Models\Concerns\InteractsWithActivityTypeImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

class ActivityType extends Model implements HasMedia
{
    use HasFactory, InteractsWithActivityTypeImages;

    public const SLUG_RPG = 'rpg';

    public const SLUG_WARGAME = 'wargame';

    public const SLUG_BOARD = 'board';

    public const SLUG_CARD = 'card';

    public const SLUG_LARP = 'larp';

    public const SLUG_DISCUSSION = 'discussion';

    public const SLUG_LECTURE = 'lecture';

    public const SLUG_WORKSHOP = 'workshop';

    public const SLUG_COMPETITION = 'competition';

    public const SLUG_SHOW = 'show';

    public const DEFAULT_MAX_PARTICIPANTS_LIMIT = 20;

    public $timestamps = false;

    protected $fillable = [
        'slug',
        'max_participants_limit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_participants_limit' => 'integer',
        ];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function slots(): BelongsToMany
    {
        return $this->belongsToMany(Slot::class, 'activity_type_slot');
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        $reflection = new \ReflectionClass(static::class);

        return collect($reflection->getConstants())
            ->filter(fn ($value, $key) => str_starts_with($key, 'SLUG_'))
            ->values()
            ->all();
    }

    public static function defaultMaxParticipantsLimitForSlug(string $slug): int
    {
        return match ($slug) {
            self::SLUG_RPG => 20,
            self::SLUG_WARGAME => 12,
            self::SLUG_BOARD => 8,
            self::SLUG_CARD => 8,
            self::SLUG_LARP => 40,
            self::SLUG_DISCUSSION => 30,
            self::SLUG_LECTURE => 100,
            self::SLUG_WORKSHOP => 24,
            self::SLUG_COMPETITION => 64,
            self::SLUG_SHOW => 100,
            default => self::DEFAULT_MAX_PARTICIPANTS_LIMIT,
        };
    }

    public static function maxParticipantsLimitForId(?int $activityTypeId): int
    {
        if ($activityTypeId === null || $activityTypeId <= 0) {
            return self::DEFAULT_MAX_PARTICIPANTS_LIMIT;
        }

        $limit = static::query()->whereKey($activityTypeId)->value('max_participants_limit');

        if ($limit === null || (int) $limit < 1) {
            return self::DEFAULT_MAX_PARTICIPANTS_LIMIT;
        }

        return (int) $limit;
    }

    public function resolvedMaxParticipantsLimit(): int
    {
        $limit = (int) ($this->max_participants_limit ?? 0);

        return $limit >= 1 ? $limit : self::DEFAULT_MAX_PARTICIPANTS_LIMIT;
    }
}
