<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagCategory extends Model
{
    public const KEY_GAME = 'game';

    public const KEY_PUBLISHER = 'publisher';

    public const KEY_WORLD = 'world';

    public const KEY_CONVENTION = 'convention';

    public const KEY_ENGINE = 'engine';

    public const KEY_TRIGGER = 'trigger';

    public const KEY_BLOCK = 'block';

    public const KEY_MISC = 'misc';

    /** @var list<string> */
    public const DEFAULT_KEYS = [
        self::KEY_GAME,
        self::KEY_PUBLISHER,
        self::KEY_WORLD,
        self::KEY_CONVENTION,
        self::KEY_ENGINE,
        self::KEY_TRIGGER,
        self::KEY_BLOCK,
        self::KEY_MISC,
    ];

    /** Categories highlighted in activity cards/event slots. */
    /** @var list<string> */
    public const ACTIVITY_HIGHLIGHT_KEYS = [
        self::KEY_GAME,
        self::KEY_WORLD,
        self::KEY_CONVENTION,
        self::KEY_ENGINE,
        self::KEY_BLOCK,
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
