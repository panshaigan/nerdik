<?php

namespace App\Filament\Admin\Resources\Activities\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('desc')
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('min_participants')
                    ->numeric()
                    ->default(null),
                TextInput::make('max_participants')
                    ->numeric()
                    ->default(null),
                TextInput::make('minimum_age')
                    ->numeric()
                    ->default(null),
                TextInput::make('price')
                    ->numeric()
                    ->default(null)
                    ->prefix('$'),
                Toggle::make('is_host_passive')
                    ->default(false),
                TextInput::make('created_by')
                    ->numeric()
                    ->default(null),
                Toggle::make('requires_approval')
                    ->required(),
                TextInput::make('cancellation_deadline_in_hours')
                    ->numeric()
                    ->default(null),
                TextInput::make('status')
                    ->required()
                    ->default('planned'),
                TextInput::make('logo_path')
                    ->default(null),
                Textarea::make('languages')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('duration_in_minutes')
                    ->numeric()
                    ->default(null),
                Toggle::make('allows_observers')
                    ->required(),
                Textarea::make('extra')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('deleted_by')
                    ->numeric()
                    ->default(null),
                TextInput::make('updated_by')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
