<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagCategory extends Model
{
    public $timestamps = false;

    public const KEY_GAME = 'game';

    public const KEY_GENRE = 'genre';

    public const KEY_SETTING = 'setting';

    public const KEY_MECHANIC = 'mechanic';

    public const KEY_FORMAT = 'format';

    public const KEY_OTHER = 'other';

    public const KEY_TRIGGER = 'trigger';

    public const KEY_TOPIC = 'topic';



    /** @var list<string> */
    public const DEFAULT_KEYS = [
        self::KEY_GAME,
        self::KEY_GENRE,
        self::KEY_SETTING,
        self::KEY_MECHANIC,
        self::KEY_FORMAT,
        self::KEY_OTHER,
        self::KEY_TRIGGER,
        self::KEY_TOPIC,
    ];

    /** Categories highlighted in activity cards. */
    /** @var list<string> */
    public const ACTIVITY_HIGHLIGHT_KEYS = [
        self::KEY_GAME,
        self::KEY_GENRE,
        self::KEY_SETTING,
        self::KEY_MECHANIC,
        self::KEY_FORMAT,
        self::KEY_OTHER,
        self::KEY_TRIGGER,
        self::KEY_TOPIC,
    ];

    protected $fillable = [
        'key',
    ];

    public function translations()
    {
        return $this->hasMany(TagCategoryTranslation::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }

    public function name(string $locale): string
    {
        $current = $this->translations->firstWhere('locale', $locale)?->label;
        if (is_string($current) && trim($current) !== '') {
            return $current;
        }

        $en = $this->translations->firstWhere('locale', 'en')?->label;
        if (is_string($en) && trim($en) !== '') {
            return $en;
        }

        return (string) $this->key;
    }
}
