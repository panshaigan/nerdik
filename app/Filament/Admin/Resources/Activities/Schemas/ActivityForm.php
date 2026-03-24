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
                TextInput::make('type')
                    ->required(),
                TextInput::make('min_participants')
                    ->numeric()
                    ->default(null),
                TextInput::make('max_participants')
                    ->numeric()
                    ->default(null),
                TextInput::make('age_limit')
                    ->numeric()
                    ->default(null),
                TextInput::make('price')
                    ->numeric()
                    ->default(null)
                    ->prefix('$'),
                TextInput::make('host_user_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('created_by')
                    ->numeric()
                    ->default(null),
                Toggle::make('is_restricted')
                    ->required(),
                TextInput::make('signoff_deadline_hours')
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
                TextInput::make('duration_minutes')
                    ->numeric()
                    ->default(null),
                Toggle::make('open_for_observers')
                    ->required(),
                TextInput::make('slug')
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
