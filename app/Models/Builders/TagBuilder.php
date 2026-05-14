<?php

namespace App\Models\Builders;

use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Additional scope methods for {@see Tag}.
 * @extends Builder<Tag>
 */
class TagBuilder extends Builder
{
    public function ofCategory(string $categoryKey): self
    {
        $categoryId = TagCategory::idByKey($categoryKey);

        return $this->where('tag_category_id', $categoryId);
    }

    public function games(): self
    {
        return $this->ofCategory(TagCategory::KEY_GAME);
    }

    public function formats(): self
    {
        return $this->ofCategory(TagCategory::KEY_FORMAT);
    }

    public function triggers(): self
    {
        return $this->ofCategory(TagCategory::KEY_TRIGGER);
    }

    public function others(): self
    {
        return $this->ofCategory(TagCategory::KEY_OTHER);
    }

    public function withRelated(): self
    {
        return $this->with('relatedTags');
    }

    public function orderedForSelector(): self
    {
        return $this->with(['translations', 'aliases', 'tagRelations', 'tagCategory.translations'])
            ->orderBy('tag_category_id')
            ->orderBy('id');
    }

    /** Tags for activity form picker: includes contexts for suggestion filtering. */
    public function forActivityFormPicker(): self
    {
        return $this->with(['translations', 'aliases', 'tagRelations', 'tagCategory.translations', 'contexts'])
            ->orderBy('tag_category_id')
            ->orderBy('id');
    }
}
