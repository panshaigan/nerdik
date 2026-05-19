<?php

namespace App\Models;

use App\Models\Builders\TagBuilder;
use App\Traits\HasMetaColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\HasBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasBuilder, HasFactory, HasMetaColumns, SoftDeletes;

    protected static string $builder = TagBuilder::class;

    protected $fillable = [
        'tag_category_id',
        'logo_path',
        'created_by',
        'updated_by',
        'context_type',
        'context_id',
    ];

    protected $appends = [
        'category',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'popularity_score' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(TagTranslation::class);
    }

    public function tagCategory(): BelongsTo
    {
        return $this->belongsTo(TagCategory::class, 'tag_category_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(TagAlias::class);
    }

    public function tagRelations(): HasMany
    {
        return $this->hasMany(TagRelation::class, 'tag_id');
    }

    /** TagRelation rows where this tag is the linked tag (`related_tag_id`, inverse side). */
    public function inverseTagRelations(): HasMany
    {
        return $this->hasMany(TagRelation::class, 'related_tag_id');
    }

    public function relatedTags(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'tag_relations',
            'tag_id',
            'related_tag_id'
        );
    }

    public function relatedToTags(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'tag_relations',
            'related_tag_id',
            'tag_id'
        );
    }

    public function activities(): MorphToMany
    {
        return $this->morphedByMany(Activity::class, 'taggable', 'taggables');
    }

    public function contexts(): HasMany
    {
        return $this->hasMany(TagContext::class);
    }

    public function contextActivities(): MorphToMany
    {
        return $this->morphedByMany(Activity::class, 'context', 'tag_contexts');
    }

    public function contextEvents(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'context', 'tag_contexts');
    }

    public function contextOrganizations(): MorphToMany
    {
        return $this->morphedByMany(Organization::class, 'context', 'tag_contexts');
    }

    public function getCategoryAttribute(): ?string
    {
        if ($this->relationLoaded('tagCategory')) {
            return $this->tagCategory?->key;
        }

        return $this->tagCategory()->value('key');
    }
}
