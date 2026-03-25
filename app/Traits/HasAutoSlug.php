<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Auto-generates a unique `slug` from a source field (default: `name`).
 *
 * - Slug is generated on create when empty.
 * - Slug is regenerated on update when the source field changes.
 * - Uses incremental suffixes (`foo-2`, `foo-3`, ...) to resolve duplicates.
 */
trait HasAutoSlug
{
    /** @var string Field used to generate the slug. */
    protected string $slugSourceField = 'name';

    /** @var string Column name for the slug. */
    protected string $slugColumn = 'slug';

    /** @var int Max base length before adding `-N` suffix. */
    protected int $slugBaseMaxLength = 190;

    public static function bootHasAutoSlug(): void
    {
        static::saving(function (Model $model) {
            $slugColumn = property_exists($model, 'slugColumn') ? $model->slugColumn : 'slug';
            $sourceField = property_exists($model, 'slugSourceField') ? $model->slugSourceField : 'name';

            if (! isset($model->{$sourceField}) || $model->{$sourceField} === null) {
                return;
            }

            $sourceValue = (string) $model->{$sourceField};
            if (trim($sourceValue) === '') {
                return;
            }

            $slug = $model->{$slugColumn} ?? null;
            $shouldRegenerate = empty($slug) || $model->isDirty($sourceField);

            if (! $shouldRegenerate) {
                return;
            }

            $slugBase = Str::slug($sourceValue);
            if ($slugBase === '') {
                $slugBase = 'item';
            }

            // Keep some space for suffixes like `-2`.
            $slugBase = Str::limit($slugBase, property_exists($model, 'slugBaseMaxLength') ? $model->slugBaseMaxLength : 190, '');
            $candidate = $slugBase;

            $query = $model->newModelQuery()->where($slugColumn, $candidate);
            if ($model->exists) {
                $query->where($model->getKeyName(), '!=', $model->getKey());
            }

            if ($query->exists()) {
                $counter = 2;
                while (true) {
                    $candidate = $slugBase.'-'.$counter;
                    $query = $model->newModelQuery()->where($slugColumn, $candidate);
                    if ($model->exists) {
                        $query->where($model->getKeyName(), '!=', $model->getKey());
                    }
                    if (! $query->exists()) {
                        break;
                    }
                    $counter++;
                }
            }

            $model->{$slugColumn} = $candidate;
        });
    }
}
