<?php

namespace App\Filament\Admin\Resources\UserProfiles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('google_id')
                    ->searchable(),
                TextColumn::make('facebook_id')
                    ->searchable(),
                TextColumn::make('avatar_path')
                    ->searchable(),
                TextColumn::make('avatar_source')
                    ->badge()
                    ->searchable(),
                TextColumn::make('avatar_cache_signature')
                    ->searchable(),
                TextColumn::make('avatar_bg_color')
                    ->searchable(),
                TextColumn::make('avatar_text_color')
                    ->searchable(),
                TextColumn::make('avatar_initials')
                    ->searchable(),
                TextColumn::make('discord_handle')
                    ->searchable(),
                TextColumn::make('current_location')
                    ->searchable(),
                TextColumn::make('timezone')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
