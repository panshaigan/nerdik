<?php

namespace App\Filament\Admin\Resources\Places\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PlaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                BelongsToSelect::country('country_id')
                    ->live(),
                BelongsToSelect::city(
                    modifyQuery: fn (Builder $query, ?string $search, Get $get): Builder => filled($get('country_id'))
                        ? $query->where('country_id', $get('country_id'))
                        : $query,
                ),
                BelongsToSelect::make('parent_id', 'parent'),
                TextInput::make('address'),
                TextInput::make('links'),
                Toggle::make('is_online')
                    ->required(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('logo_path'),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                BelongsToSelect::user('created_by'),
                BelongsToSelect::user('updated_by'),
                BelongsToSelect::user('deleted_by'),
            ]);
    }
}
