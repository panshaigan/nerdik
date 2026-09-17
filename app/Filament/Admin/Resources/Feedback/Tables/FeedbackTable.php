<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Feedback\Tables;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Filament\Tables\Filters\BelongsToFilter;
use App\Filament\Tables\Filters\CommonFilters;
use App\Models\Feedback;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeedbackTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackStatus $state): string => $state->label())
                    ->color(fn (FeedbackStatus $state): string => match ($state) {
                        FeedbackStatus::Open => 'warning',
                        FeedbackStatus::Resolved => 'success',
                    })
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('email')
                    ->label('Reporter')
                    ->description(fn (Feedback $record): ?string => $record->user?->displayName())
                    ->formatStateUsing(fn (?string $state, Feedback $record): string => $record->reporterEmail() ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $builder) use ($search): void {
                            $builder
                                ->where('email', 'like', "%{$search}%")
                                ->orWhereHas('user', fn (Builder $userQuery): Builder => $userQuery
                                    ->where('email', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%"));
                        });
                    }),
                TextColumn::make('replied_at')
                    ->label('Replied')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(FeedbackType::options()),
                SelectFilter::make('status')
                    ->options(FeedbackStatus::options()),
                BelongsToFilter::user('user_id'),
                CommonFilters::dateRange('created_at', 'Submitted'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
