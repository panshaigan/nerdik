<?php

namespace App\Support\Filament;

use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FilamentSearch
{
    /** @var array<string, string> */
    private const RELATIONSHIP_SEARCH_METHODS = [
        'tag' => 'applyTagSearch',
        'relatedTag' => 'applyTagSearch',
        'tagCategory' => 'applyTagCategorySearch',
        'category' => 'applyTagCategorySearch',
        'country' => 'applyCountrySearch',
        'city' => 'applyCitySearch',
        'activityType' => 'applyActivityTypeSearch',
        'place' => 'applyPlaceSearch',
        'slot' => 'applySlotSearch',
        'acceptedSlot' => 'applySlotSearch',
        'proposal' => 'applyActivityProposalSearch',
    ];

    public static function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyBlankSearchGuard(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query->whereRaw('0 = 1');
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function whereHasRelationship(Builder $query, string $relationship, string $search): Builder
    {
        $method = self::RELATIONSHIP_SEARCH_METHODS[$relationship] ?? null;

        if ($method === null) {
            throw new InvalidArgumentException("No table search configured for relationship [{$relationship}].");
        }

        return $query->whereHas($relationship, fn (Builder $relationshipQuery): Builder => self::{$method}($relationshipQuery, $search));
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyTagSearch(Builder $query, string $search): Builder
    {
        $operator = self::likeOperator();
        $term = '%'.$search.'%';

        return $query->where(function (Builder $outer) use ($operator, $term): void {
            $outer->whereHas('translations', function (Builder $translationQuery) use ($operator, $term): void {
                $translationQuery->where('label', $operator, $term)
                    ->orWhere('slug', $operator, $term);
            })->orWhereHas('aliases', function (Builder $aliasQuery) use ($operator, $term): void {
                $aliasQuery->where('alias', $operator, $term);
            });
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyTagCategorySearch(Builder $query, string $search): Builder
    {
        $operator = self::likeOperator();
        $term = '%'.$search.'%';

        return $query->where(function (Builder $outer) use ($operator, $term): void {
            $outer->where('key', $operator, $term)
                ->orWhereHas('translations', function (Builder $translationQuery) use ($operator, $term): void {
                    $translationQuery->where('label', $operator, $term);
                });
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyCountrySearch(Builder $query, string $search): Builder
    {
        $operator = self::likeOperator();
        $term = '%'.$search.'%';

        return $query->where(function (Builder $outer) use ($operator, $term): void {
            $outer->where('iso_alpha2', $operator, $term)
                ->orWhereHas('translations', function (Builder $translationQuery) use ($operator, $term): void {
                    $translationQuery->where('name', $operator, $term);
                });
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyCitySearch(Builder $query, string $search): Builder
    {
        $operator = self::likeOperator();
        $term = '%'.$search.'%';

        return $query->where(function (Builder $outer) use ($operator, $term): void {
            $outer->where('slug', $operator, $term)
                ->orWhereHas('translations', function (Builder $translationQuery) use ($operator, $term): void {
                    $translationQuery->where('name', $operator, $term);
                });
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyActivityTypeSearch(Builder $query, string $search): Builder
    {
        $operator = self::likeOperator();

        return $query->where('slug', $operator, '%'.$search.'%');
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyPlaceSearch(Builder $query, string $search): Builder
    {
        $operator = self::likeOperator();
        $term = '%'.$search.'%';

        return $query->where(function (Builder $outer) use ($operator, $term): void {
            $outer->where('name', $operator, $term)
                ->orWhereHas('parent', function (Builder $parentQuery) use ($operator, $term): void {
                    $parentQuery->where('name', $operator, $term);
                })
                ->orWhereIn('parent_id', Place::query()
                    ->where('type', Place::TYPE_VENUE)
                    ->where('name', $operator, $term)
                    ->select('id'));
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applySlotSearch(Builder $query, string $search): Builder
    {
        $operator = self::likeOperator();
        $term = '%'.$search.'%';

        return $query->where(function (Builder $outer) use ($operator, $term): void {
            $outer->where('name', $operator, $term)
                ->orWhereHas('event', function (Builder $eventQuery) use ($operator, $term): void {
                    $eventQuery->where('name', $operator, $term);
                });
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyActivityProposalSearch(Builder $query, string $search): Builder
    {
        $operator = self::likeOperator();
        $term = '%'.$search.'%';

        return $query->where(function (Builder $outer) use ($operator, $term, $search): void {
            if (is_numeric($search)) {
                $outer->whereKey((int) $search);
            }

            $outer->orWhereHas('activity', function (Builder $activityQuery) use ($operator, $term): void {
                $activityQuery->where('name', $operator, $term);
            })->orWhereHas('event', function (Builder $eventQuery) use ($operator, $term): void {
                $eventQuery->where('name', $operator, $term);
            });
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function whereTagHasTranslationLabel(Builder $query, string $search, string $locale): Builder
    {
        $operator = self::likeOperator();
        $term = '%'.$search.'%';

        return $query->where(function (Builder $outer) use ($operator, $term, $locale): void {
            $outer->whereHas('translations', function (Builder $translationQuery) use ($operator, $term, $locale): void {
                $translationQuery->where('locale', $locale)
                    ->where(function (Builder $inner) use ($operator, $term): void {
                        $inner->where('label', $operator, $term)
                            ->orWhere('slug', $operator, $term);
                    });
            })->orWhereHas('aliases', function (Builder $aliasQuery) use ($operator, $term, $locale): void {
                $aliasQuery->where('locale', $locale)
                    ->where('alias', $operator, $term);
            });
        });
    }
}
