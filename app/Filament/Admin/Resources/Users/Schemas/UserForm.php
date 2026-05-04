<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('profile.google_id')
                    ->default(null),
                TextInput::make('profile.facebook_id')
                    ->default(null),
                TextInput::make('profile.avatar_path')
                    ->default(null),
                TextInput::make('profile.avatar_source')
                    ->default('generated'),
                TextInput::make('profile.avatar_cache_signature')
                    ->default(null),
                TextInput::make('profile.google_avatar_url')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('profile.facebook_avatar_url')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('profile.avatar_bg_color')
                    ->default('#1d4ed8'),
                TextInput::make('profile.avatar_text_color')
                    ->default('#ffffff'),
                TextInput::make('profile.discord_handle')
                    ->default(null),
                TextInput::make('profile.current_location')
                    ->default(null),
                TextInput::make('profile.timezone')
                    ->default(null),
                Textarea::make('profile.languages')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
