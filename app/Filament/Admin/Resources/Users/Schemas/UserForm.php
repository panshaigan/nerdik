<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nickname')
                    ->required(),
                TextInput::make('name')
                    ->label('Real name (optional)'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Toggle::make('is_admin')
                    ->required(),
                Toggle::make('is_event_organizer')
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('google_id')
                    ->default(null),
                TextInput::make('facebook_id')
                    ->default(null),
                TextInput::make('avatar_path')
                    ->default(null),
                TextInput::make('discord_handle')
                    ->default(null),
                TextInput::make('current_location')
                    ->default(null),
                TextInput::make('timezone')
                    ->default(null),
                Textarea::make('languages')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
