<?php

namespace App\Filament\Admin\Resources\Places\Schemas;

use App\Models\City;
use App\Models\Country;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('created_by')
                    ->numeric()
                    ->default(null),
                TextInput::make('name')
                    ->required(),
                Select::make('country_id')
                    ->label('Country (location)')
                    ->options(fn () => Country::query()->with('translations')->orderBy('iso_alpha2')->get()
                        ->mapWithKeys(fn (Country $c) => [$c->id => $c->name()]))
                    ->searchable()
                    ->nullable(),
                Select::make('city_id')
                    ->label('City (location)')
                    ->options(fn () => City::query()->with(['translations', 'country'])->get()
                        ->mapWithKeys(fn (City $c) => [$c->id => $c->name()]))
                    ->searchable()
                    ->nullable(),
                TextInput::make('parent_id')
                    ->numeric()
                    ->default(null),
                Select::make('type')
                    ->options([
                        'country' => 'Country',
                        'state' => 'State',
                        'city' => 'City',
                        'venue' => 'Venue',
                        'room' => 'Room',
                    ])
                    ->required(),
                TextInput::make('links')
                    ->default(null),
                Textarea::make('desc')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_online')
                    ->required(),
                TextInput::make('latitude')
                    ->numeric()
                    ->default(null),
                TextInput::make('longitude')
                    ->numeric()
                    ->default(null),
                TextInput::make('logo_path')
                    ->default(null),
                TextInput::make('deleted_by')
                    ->numeric()
                    ->default(null),
                TextInput::make('updated_by')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
