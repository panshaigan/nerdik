<?php

namespace App\Filament\Admin\Resources\Tags\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use App\Filament\Tables\Filters\BelongsToFilter;
use App\Filament\Tables\Filters\CommonFilters;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Support\Filament\FilamentSearch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'tagCategory.translations',
            'translations',
            'creator',
            'updater',
            'deleter',
        ])
            ->columns([
                TextColumn::make('label_en')
                    ->label('English')
                    ->state(fn (Tag $record): string => $record->displayLabel('en'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => FilamentSearch::whereTagHasTranslationLabel($query, $search, 'en')),
                TextColumn::make('label_pl')
                    ->label('Polish')
                    ->state(fn (Tag $record): string => $record->displayLabel('pl'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => FilamentSearch::whereTagHasTranslationLabel($query, $search, 'pl')),
                BelongsToColumn::record('tagCategory', label: 'Category', searchable: true),
                SpatieMediaLibraryImageColumn::make('images')
                    ->collection('images')
                    ->conversion('webp')
                    ->limit(3)
                    ->stacked(),
                TextColumn::make('popularity_score')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                BelongsToColumn::user('created_by'),
                BelongsToColumn::user('updated_by'),
                BelongsToColumn::user('deleted_by'),
            ])
            ->filters([
                BelongsToFilter::tagCategory(),
                SelectFilter::make('locale')
                    ->options(fn (): array => TagTranslation::query()
                        ->whereNotNull('locale')
                        ->distinct()
                        ->orderBy('locale')
                        ->pluck('locale', 'locale')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $builder): Builder => $builder->whereHas(
                            'translations',
                            fn (Builder $translationQuery): Builder => $translationQuery->where('locale', $data['value']),
                        ),
                    )),
                ...CommonFilters::auditUserFilters(),
                ...CommonFilters::auditTimestampFilters(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
