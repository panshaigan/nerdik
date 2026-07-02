<?php

namespace App\Support\Filament;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FilamentFilterAttributeResolver
{
    public static function resolveBelongsToForeignKey(Model $model, string $filterName): ?string
    {
        if (self::modelHasColumn($model, $filterName)) {
            return $filterName;
        }

        if (! $model->isRelation($filterName)) {
            return null;
        }

        $relationship = $model->{$filterName}();

        if (! $relationship instanceof BelongsTo) {
            return null;
        }

        $foreignKey = $relationship->getForeignKeyName();

        if (! self::modelHasColumn($model, $foreignKey)) {
            return null;
        }

        return $foreignKey;
    }

    private static function modelHasColumn(Model $model, string $column): bool
    {
        if ($model->hasAttribute($column)) {
            return true;
        }

        return in_array($column, $model->getFillable(), true)
            || array_key_exists($column, $model->getCasts());
    }
}
