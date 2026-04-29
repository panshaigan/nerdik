<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    use HasFactory;

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

    public $timestamps = false;

    protected $fillable = [
        'slug',
    ];

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function slots()
    {
        return $this->belongsToMany(Slot::class, 'activity_type_slot');
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public static function slugs(): array
    {
        $reflection = new \ReflectionClass(static::class);

        return collect($reflection->getConstants())
            ->filter(fn ($value, $key) => str_starts_with($key, 'SLUG_'))
            ->values()
            ->all();
    }
}
