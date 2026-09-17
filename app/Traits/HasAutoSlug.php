<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Auto-generates a unique `slug` from a source field (default: `name`).
 *
 * - Slug is generated on create when empty.
 * - Slug is regenerated on update when the source field changes.
 * - Uses incremental suffixes (`foo-2`, `foo-3`, ...) to resolve duplicates.
 * - Uniqueness considers live rows only (soft-deleted slugs may be reused).
 * - On restore, re-uniquifies the slug if another live row already holds it.
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

            $model->{$slugColumn} = static::makeUniqueSlug(
                $sourceValue,
                $model->exists ? (int) $model->getKey() : null,
            );
        });

        if (static::modelUsesSoftDeletes()) {
            static::restoring(function (Model $model) {
                $slugColumn = property_exists($model, 'slugColumn') ? $model->slugColumn : 'slug';
                $sourceField = property_exists($model, 'slugSourceField') ? $model->slugSourceField : 'name';
                $slug = $model->{$slugColumn} ?? null;

                if ($slug === null || $slug === '') {
                    return;
                }

                $taken = static::query()
                    ->where($slugColumn, $slug)
                    ->where($model->getKeyName(), '!=', $model->getKey())
                    ->exists();

                if (! $taken) {
                    return;
                }

                $sourceValue = isset($model->{$sourceField}) && $model->{$sourceField} !== null
                    ? (string) $model->{$sourceField}
                    : (string) $slug;

                if (trim($sourceValue) === '') {
                    $sourceValue = (string) $slug;
                }

                $model->{$slugColumn} = static::makeUniqueSlug(
                    $sourceValue,
                    (int) $model->getKey(),
                );
            });
        }
    }

    public static function makeUniqueSlug(string $sourceValue, ?int $ignoreId = null): string
    {
        /** @var Model $model */
        $model = new static;

        $slugColumn = property_exists($model, 'slugColumn') ? $model->slugColumn : 'slug';
        $maxLength = property_exists($model, 'slugBaseMaxLength') ? $model->slugBaseMaxLength : 190;

        $slugBase = Str::slug($sourceValue);
        if ($slugBase === '') {
            $slugBase = 'item';
        }

        $slugBase = Str::limit($slugBase, $maxLength, '');
        $candidate = $slugBase;

        $query = static::query()->where($slugColumn, $candidate);
        if ($ignoreId !== null) {
            $query->where($model->getKeyName(), '!=', $ignoreId);
        }

        if ($query->exists()) {
            $counter = 2;
            while (true) {
                $candidate = $slugBase.'-'.$counter;
                $query = static::query()->where($slugColumn, $candidate);
                if ($ignoreId !== null) {
                    $query->where($model->getKeyName(), '!=', $ignoreId);
                }
                if (! $query->exists()) {
                    break;
                }
                $counter++;
            }
        }

        return $candidate;
    }

    private static function modelUsesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class), true);
    }
}
