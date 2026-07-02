<?php

namespace App\Filament\Tables\Columns;

use App\Models\Place;
use App\Models\Slot;
use App\Models\TagContext;
use App\Models\User;
use App\Support\Filament\FilamentRecordLabel;
use App\Support\Filament\FilamentSearch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BelongsToColumn
{
    /** @var array<string, string> */
    private const USER_COLUMN_RELATIONSHIPS = [
        'user_id' => 'user',
        'created_by' => 'creator',
        'updated_by' => 'updater',
        'deleted_by' => 'deleter',
        'cancelled_by' => 'canceller',
    ];

    /** @var list<string> */
    public const AUDIT_USER_RELATIONSHIPS = [
        'creator',
        'updater',
        'deleter',
        'canceller',
    ];

    /** @var array<string, list<string>> */
    private const SORT_COLUMNS_BY_RELATIONSHIP = [
        'activityType' => ['activityType.slug'],
        'tagCategory' => ['tagCategory.key'],
        'category' => ['category.key'],
        'creator' => ['creator.nickname'],
        'updater' => ['updater.nickname'],
        'deleter' => ['deleter.nickname'],
        'canceller' => ['canceller.nickname'],
        'user' => ['user.nickname'],
        'proposal' => ['proposal.id'],
        'tag' => ['tag.id'],
        'relatedTag' => ['relatedTag.id'],
        'country' => ['country.iso_alpha2'],
        'city' => ['city.slug'],
        'place' => ['place.name'],
        'slot' => ['slot.name'],
        'acceptedSlot' => ['acceptedSlot.name'],
    ];

    /**
     * @param  list<string>  $relationships
     */
    public static function withEagerLoads(Table $table, array $relationships): Table
    {
        if ($relationships === []) {
            return $table;
        }

        return $table->modifyQueryUsing(function (Builder $query) use ($relationships): Builder {
            return $query->with($relationships);
        });
    }

    /**
     * @param  list<string>|null  $sortColumns
     */
    public static function record(
        string $relationship,
        ?string $label = null,
        ?array $sortColumns = null,
        bool $searchable = false,
    ): TextColumn {
        $column = TextColumn::make($relationship)
            ->label($label ?? Str::headline($relationship))
            ->formatStateUsing(fn (?Model $state): ?string => $state instanceof Model
                ? FilamentRecordLabel::for($state)
                : null)
            ->sortable($sortColumns ?? self::SORT_COLUMNS_BY_RELATIONSHIP[$relationship] ?? ["{$relationship}.name"]);

        if ($searchable) {
            $column->searchable(query: fn (Builder $query, string $search): Builder => FilamentSearch::whereHasRelationship($query, $relationship, $search));
        }

        return $column;
    }

    public static function user(string $column): TextColumn
    {
        $relationship = self::USER_COLUMN_RELATIONSHIPS[$column]
            ?? Str::camel(Str::beforeLast($column, '_id'));

        return TextColumn::make($relationship)
            ->label(Str::headline($column))
            ->formatStateUsing(fn (?User $state): ?string => $state instanceof User
                ? FilamentRecordLabel::for($state)
                : null)
            ->sortable(self::SORT_COLUMNS_BY_RELATIONSHIP[$relationship] ?? ["{$relationship}.nickname"]);
    }

    public static function place(string $relationship = 'place'): TextColumn
    {
        return TextColumn::make($relationship)
            ->label(Str::headline($relationship))
            ->formatStateUsing(fn (?Place $state): ?string => $state instanceof Place
                ? FilamentRecordLabel::for($state)
                : null)
            ->sortable(self::SORT_COLUMNS_BY_RELATIONSHIP[$relationship] ?? ["{$relationship}.name"])
            ->searchable(query: fn (Builder $query, string $search): Builder => FilamentSearch::whereHasRelationship($query, $relationship, $search));
    }

    public static function placeName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('Name'))
            ->state(fn (Place $record): string => $record->venueRoomLabel())
            ->sortable()
            ->searchable(query: fn (Builder $query, string $search): Builder => FilamentSearch::applyPlaceSearch($query, $search));
    }

    public static function slot(string $relationship = 'slot'): TextColumn
    {
        return TextColumn::make($relationship)
            ->label(Str::headline($relationship))
            ->formatStateUsing(fn (?Slot $state): ?string => $state instanceof Slot
                ? FilamentRecordLabel::slot($state)
                : null)
            ->sortable(self::SORT_COLUMNS_BY_RELATIONSHIP[$relationship] ?? ["{$relationship}.name"])
            ->searchable(query: fn (Builder $query, string $search): Builder => FilamentSearch::whereHasRelationship($query, $relationship, $search));
    }

    public static function slotRecord(): TextColumn
    {
        return TextColumn::make('slot_label')
            ->label(__('Slot'))
            ->state(fn (Slot $record): string => FilamentRecordLabel::slot($record))
            ->sortable(['name'])
            ->searchable(['name']);
    }

    public static function morphContext(): TextColumn
    {
        return TextColumn::make('context')
            ->label(__('Context'))
            ->formatStateUsing(function (?Model $state, TagContext $record): string {
                if ($state instanceof Model) {
                    return FilamentRecordLabel::for($state);
                }

                return "{$record->context_type} #{$record->context_id}";
            })
            ->sortable(['context_type', 'context_id']);
    }
}
