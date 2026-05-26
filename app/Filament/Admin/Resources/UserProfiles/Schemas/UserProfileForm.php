<?php

namespace App\Filament\Admin\Resources\UserProfiles\Schemas;

use App\Enums\AvatarSource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('google_id'),
                TextInput::make('facebook_id'),
                TextInput::make('avatar_path'),
                Select::make('avatar_source')
                    ->options(AvatarSource::class)
                    ->default('generated')
                    ->required(),
                TextInput::make('avatar_cache_signature'),
                Textarea::make('google_avatar_url')
                    ->columnSpanFull(),
                Textarea::make('facebook_avatar_url')
                    ->columnSpanFull(),
                TextInput::make('avatar_bg_color'),
                TextInput::make('avatar_text_color'),
                TextInput::make('avatar_initials'),
                TextInput::make('discord_handle'),
                TextInput::make('current_location'),
                TextInput::make('timezone'),
                Textarea::make('languages')
                    ->columnSpanFull(),
                TextInput::make('notification_preferences'),
            ]);
    }
}
