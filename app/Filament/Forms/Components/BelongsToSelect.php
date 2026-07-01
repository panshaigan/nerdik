<?php

namespace App\Filament\Forms\Components;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\City;
use App\Models\Country;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use App\Support\Filament\FilamentRecordLabel;
use App\Support\Filament\FilamentSearch;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BelongsToSelect
{
    private const SEARCH_DEBOUNCE_MS = 300;

    private const INITIAL_SUGGESTIONS_LIMIT = 20;

    private const SEARCH_OPTIONS_LIMIT = 50;

    /** @var array<string, string> */
    private const USER_COLUMN_RELATIONSHIPS = [
        'user_id' => 'user',
        'created_by' => 'creator',
        'updated_by' => 'updater',
        'deleted_by' => 'deleter',
        'cancelled_by' => 'canceller',
    ];

    /**
     * @param  list<string>  $searchColumns
     */
    public static function make(
        string $name,
        ?string $relationship = null,
        string $titleAttribute = 'name',
        array $searchColumns = [],
    ): Select {
        $relationship ??= Str::camel(Str::beforeLast($name, '_id'));
        $searchColumns = $searchColumns !== [] ? $searchColumns : [$titleAttribute];

        return self::applyDefaults(
            self::configureRelationshipSearchResults(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        $titleAttribute,
                        self::orderRelationshipByTitle($titleAttribute),
                    )
                    ->searchable($searchColumns),
            ),
        );
    }

    public static function user(string $name, ?string $relationship = null): Select
    {
        $relationship ??= self::USER_COLUMN_RELATIONSHIPS[$name] ?? Str::camel(Str::beforeLast($name, '_id'));

        return self::applyDefaults(
            self::configureRelationshipSearchResults(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'nickname',
                        self::orderRelationshipByTitle('nickname'),
                    )
                    ->searchable(['nickname', 'email', 'name'])
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => FilamentRecordLabel::for($record)),
            ),
        );
    }

    public static function tag(string $name = 'tag_id', string $relationship = 'tag'): Select
    {
        return self::applyDefaults(
            self::configureTranslatedSearch(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'id',
                        self::preloadRelationshipModifier(),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Tag $record): string => FilamentRecordLabel::for($record)),
                searchCallback: function (Builder $query, string $search): Builder {
                    $operator = FilamentSearch::likeOperator();
                    $term = '%'.$search.'%';

                    return $query->where(function (Builder $outer) use ($operator, $term): void {
                        $outer->whereHas('translations', function (Builder $translationQuery) use ($operator, $term): void {
                            $translationQuery->where('label', $operator, $term)
                                ->orWhere('slug', $operator, $term);
                        })->orWhereHas('aliases', function (Builder $aliasQuery) use ($operator, $term): void {
                            $aliasQuery->where('alias', $operator, $term);
                        });
                    });
                },
            ),
        );
    }

    public static function tagCategory(string $name = 'tag_category_id', string $relationship = 'category'): Select
    {
        return self::applyDefaults(
            self::configureTranslatedSearch(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'key',
                        self::preloadRelationshipModifier(),
                    )
                    ->getOptionLabelFromRecordUsing(fn (TagCategory $record): string => FilamentRecordLabel::for($record)),
                searchCallback: function (Builder $query, string $search): Builder {
                    $operator = FilamentSearch::likeOperator();
                    $term = '%'.$search.'%';

                    return $query->where(function (Builder $outer) use ($operator, $term): void {
                        $outer->where('key', $operator, $term)
                            ->orWhereHas('translations', function (Builder $translationQuery) use ($operator, $term): void {
                                $translationQuery->where('label', $operator, $term);
                            });
                    });
                },
            ),
        );
    }

    public static function country(string $name = 'country_id', string $relationship = 'country'): Select
    {
        return self::applyDefaults(
            self::configureTranslatedSearch(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'iso_alpha2',
                        self::preloadRelationshipModifier(),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Country $record): string => FilamentRecordLabel::for($record)),
                searchCallback: function (Builder $query, string $search): Builder {
                    $operator = FilamentSearch::likeOperator();
                    $term = '%'.$search.'%';

                    return $query->where(function (Builder $outer) use ($operator, $term): void {
                        $outer->where('iso_alpha2', $operator, $term)
                            ->orWhereHas('translations', function (Builder $translationQuery) use ($operator, $term): void {
                                $translationQuery->where('name', $operator, $term);
                            });
                    });
                },
            ),
        );
    }

    /**
     * @param  (Closure(Builder, ?string): Builder)|null  $modifyQuery
     */
    public static function city(
        string $name = 'city_id',
        string $relationship = 'city',
        ?Closure $modifyQuery = null,
    ): Select {
        return self::applyDefaults(
            self::configureTranslatedSearch(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'slug',
                        self::preloadRelationshipModifier($modifyQuery),
                    )
                    ->getOptionLabelFromRecordUsing(fn (City $record): string => FilamentRecordLabel::for($record)),
                searchCallback: function (Builder $query, string $search): Builder {
                    $operator = FilamentSearch::likeOperator();
                    $term = '%'.$search.'%';

                    return $query->where(function (Builder $outer) use ($operator, $term): void {
                        $outer->where('slug', $operator, $term)
                            ->orWhereHas('translations', function (Builder $translationQuery) use ($operator, $term): void {
                                $translationQuery->where('name', $operator, $term);
                            });
                    });
                },
                modifyQuery: $modifyQuery,
            ),
        );
    }

    public static function activityType(string $name = 'activity_type_id', ?string $relationship = 'activityType'): Select
    {
        if ($relationship === null) {
            return self::applyDefaults(
                Select::make($name)
                    ->searchable()
                    ->options(function (): array {
                        return ActivityType::query()
                            ->orderBy('slug')
                            ->limit(self::INITIAL_SUGGESTIONS_LIMIT)
                            ->get()
                            ->mapWithKeys(fn (ActivityType $record): array => [
                                $record->getKey() => FilamentRecordLabel::for($record),
                            ])
                            ->all();
                    })
                    ->getSearchResultsUsing(function (string $search): array {
                        $query = ActivityType::query()->orderBy('slug');
                        $limit = blank($search) ? self::INITIAL_SUGGESTIONS_LIMIT : self::SEARCH_OPTIONS_LIMIT;

                        if (filled($search)) {
                            $operator = FilamentSearch::likeOperator();
                            $query->where('slug', $operator, '%'.$search.'%');
                        }

                        return $query
                            ->limit($limit)
                            ->get()
                            ->mapWithKeys(fn (ActivityType $record): array => [
                                $record->getKey() => FilamentRecordLabel::for($record),
                            ])
                            ->all();
                    })
                    ->getOptionLabelUsing(fn ($value): ?string => ActivityType::query()->find($value)?->slug),
            );
        }

        return self::applyDefaults(
            self::configureRelationshipSearchResults(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'slug',
                        self::orderRelationshipByTitle('slug'),
                    )
                    ->searchable(['slug'])
                    ->getOptionLabelFromRecordUsing(fn (ActivityType $record): string => FilamentRecordLabel::for($record)),
            ),
        );
    }

    public static function activityProposal(
        string $name = 'activity_proposal_id',
        string $relationship = 'proposal',
    ): Select {
        return self::applyDefaults(
            self::configureTranslatedSearch(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'id',
                        self::preloadRelationshipModifier(),
                    )
                    ->getOptionLabelFromRecordUsing(fn (ActivityProposal $record): string => FilamentRecordLabel::for($record)),
                searchCallback: function (Builder $query, string $search): Builder {
                    $operator = FilamentSearch::likeOperator();
                    $term = '%'.$search.'%';

                    return $query
                        ->with(['activity', 'event'])
                        ->where(function (Builder $outer) use ($operator, $term, $search): void {
                            if (is_numeric($search)) {
                                $outer->whereKey((int) $search);
                            }

                            $outer->orWhereHas('activity', function (Builder $activityQuery) use ($operator, $term): void {
                                $activityQuery->where('name', $operator, $term);
                            })->orWhereHas('event', function (Builder $eventQuery) use ($operator, $term): void {
                                $eventQuery->where('name', $operator, $term);
                            });
                        });
                },
            ),
        );
    }

    public static function place(string $name = 'place_id', string $relationship = 'place'): Select
    {
        return self::applyDefaults(
            self::configurePlaceSearch(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'name',
                        self::preloadRelationshipModifier(),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Place $record): string => FilamentRecordLabel::for($record)),
            ),
        );
    }

    public static function event(string $name = 'event_id', string $relationship = 'event'): Select
    {
        return self::applyDefaults(
            self::configureRelationshipSearchResults(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'name',
                        self::orderRelationshipByTitle('name'),
                    )
                    ->searchable(['name'])
                    ->getOptionLabelFromRecordUsing(fn (Event $record): string => FilamentRecordLabel::for($record)),
            ),
        );
    }

    public static function activity(string $name = 'activity_id', string $relationship = 'activity'): Select
    {
        return self::applyDefaults(
            self::configureRelationshipSearchResults(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'name',
                        self::orderRelationshipByTitle('name'),
                    )
                    ->searchable(['name'])
                    ->getOptionLabelFromRecordUsing(fn (Activity $record): string => FilamentRecordLabel::for($record)),
            ),
        );
    }

    public static function slot(string $name = 'slot_id', string $relationship = 'slot'): Select
    {
        return self::applyDefaults(
            self::configureTranslatedSearch(
                Select::make($name)
                    ->relationship(
                        $relationship,
                        'name',
                        self::orderRelationshipByTitle('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Slot $record): string => FilamentRecordLabel::for($record)),
                searchCallback: function (Builder $query, string $search): Builder {
                    $operator = FilamentSearch::likeOperator();
                    $term = '%'.$search.'%';

                    return $query
                        ->with('event')
                        ->where(function (Builder $outer) use ($operator, $term): void {
                            $outer->where('name', $operator, $term)
                                ->orWhereHas('event', function (Builder $eventQuery) use ($operator, $term): void {
                                    $eventQuery->where('name', $operator, $term);
                                });
                        });
                },
                modifyQuery: fn (Builder $query, ?string $search): Builder => $query->with('event'),
            ),
        );
    }

    private static function applyDefaults(Select $select): Select
    {
        return $select
            ->preload()
            ->searchDebounce(self::SEARCH_DEBOUNCE_MS)
            ->searchPrompt(__('Type to search…'))
            ->optionsLimit(self::INITIAL_SUGGESTIONS_LIMIT);
    }

    /**
     * @return Closure(Builder, ?string): Builder
     */
    private static function orderRelationshipByTitle(string $titleAttribute): Closure
    {
        return function (Builder $query, ?string $search) use ($titleAttribute): Builder {
            if (blank($search) && empty($query->getQuery()->orders)) {
                $query->orderBy($query->qualifyColumn($titleAttribute));
            }

            return $query;
        };
    }

    /**
     * @param  (Closure(Builder, ?string): Builder)|null  $modifyQuery
     * @return Closure(Builder, ?string): Builder
     */
    private static function preloadRelationshipModifier(?Closure $modifyQuery = null): Closure
    {
        if ($modifyQuery !== null) {
            return $modifyQuery;
        }

        return function (Builder $query, ?string $search): Builder {
            if (blank($search) && empty($query->getQuery()->orders)) {
                $query->orderBy($query->getModel()->getQualifiedKeyName());
            }

            return $query;
        };
    }

    private static function configureRelationshipSearchResults(Select $select): Select
    {
        $select->getSearchResultsUsing(function (Select $component, ?string $search): array {
            $search ??= '';
            $component->optionsLimit(
                blank($search) ? self::INITIAL_SUGGESTIONS_LIMIT : self::SEARCH_OPTIONS_LIMIT,
            );

            return $component->getSearchResultsFromRelationship($search);
        });

        return $select;
    }

    /**
     * @return array<int|string, string>
     */
    private static function mapRecordsToOptions(Select $component, Builder $query, int $limit): array
    {
        if (empty($query->getQuery()->orders)) {
            $query->orderBy($query->getModel()->getQualifiedKeyName());
        }

        return $query
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (Model $record): array => [
                $record->getKey() => $component->getOptionLabelFromRecord($record),
            ])
            ->all();
    }

    private static function configurePlaceSearch(Select $select): Select
    {
        $select->searchable();

        $select->getSearchResultsUsing(function (Select $component, string $search): array {
            $relationship = $component->getRelationship();

            if ($relationship === null) {
                return [];
            }

            $relationshipQuery = $relationship->getRelated()->newQuery()->with('parent');

            if (blank($search)) {
                return self::mapPlaceRecordsToOptions(
                    $component,
                    $relationshipQuery->orderBy('name'),
                    self::INITIAL_SUGGESTIONS_LIMIT,
                );
            }

            $operator = FilamentSearch::likeOperator();
            $term = '%'.$search.'%';

            $relationshipQuery->where(function (Builder $outer) use ($operator, $term): void {
                $outer->where('name', $operator, $term)
                    ->orWhereHas('parent', function (Builder $parentQuery) use ($operator, $term): void {
                        $parentQuery->where('name', $operator, $term);
                    })
                    ->orWhereIn('parent_id', Place::query()
                        ->where('type', Place::TYPE_VENUE)
                        ->where('name', $operator, $term)
                        ->select('id'));
            });

            return self::mapPlaceRecordsToOptions(
                $component,
                $relationshipQuery,
                self::SEARCH_OPTIONS_LIMIT,
            );
        });

        return $select;
    }

    /**
     * @return array<int|string, string>
     */
    private static function mapPlaceRecordsToOptions(Select $component, Builder $query, int $limit): array
    {
        $places = $query
            ->limit($limit)
            ->get();

        $venueIds = $places
            ->filter(fn (Place $place): bool => $place->type === Place::TYPE_VENUE)
            ->pluck('id');

        if ($venueIds->isNotEmpty()) {
            $existingIds = $places->pluck('id');
            $rooms = Place::query()
                ->with('parent')
                ->whereIn('parent_id', $venueIds)
                ->whereNotIn('id', $existingIds)
                ->orderBy('name')
                ->limit(max(0, $limit - $places->count()))
                ->get();

            $places = $places->concat($rooms);
        }

        return $places
            ->unique('id')
            ->take($limit)
            ->mapWithKeys(fn (Place $record): array => [
                $record->getKey() => $component->getOptionLabelFromRecord($record),
            ])
            ->all();
    }

    /**
     * @param  Closure(Builder, string): Builder  $searchCallback
     * @param  (Closure(Builder, ?string): Builder)|null  $modifyQuery
     */
    private static function configureTranslatedSearch(
        Select $select,
        Closure $searchCallback,
        ?Closure $modifyQuery = null,
    ): Select {
        $select->searchable();

        $select->getSearchResultsUsing(function (Select $component, string $search) use ($searchCallback, $modifyQuery): array {
            $relationship = $component->getRelationship();

            if ($relationship === null) {
                return [];
            }

            $relationshipQuery = $relationship->getRelated()->newQuery();

            if ($modifyQuery !== null) {
                $relationshipQuery = $component->evaluate($modifyQuery, [
                    'query' => $relationshipQuery,
                    'search' => blank($search) ? null : $search,
                ]) ?? $relationshipQuery;
            }

            if (blank($search)) {
                return self::mapRecordsToOptions(
                    $component,
                    $relationshipQuery,
                    self::INITIAL_SUGGESTIONS_LIMIT,
                );
            }

            $relationshipQuery = $searchCallback($relationshipQuery, $search);

            return self::mapRecordsToOptions(
                $component,
                $relationshipQuery,
                self::SEARCH_OPTIONS_LIMIT,
            );
        });

        return $select;
    }
}
