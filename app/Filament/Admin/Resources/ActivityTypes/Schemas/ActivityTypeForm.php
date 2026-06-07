<?php

namespace App\Filament\Admin\Resources\ActivityTypes\Schemas;

use App\Models\ActivityType;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ActivityTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                Section::make('Activity fallback images')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('images')
                            ->collection('images')
                            ->multiple()
                            ->image()
                            ->reorderable()
                            ->responsiveImages()
                            ->conversion('webp')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Event listing catalog')
                    ->description('Default images organizers can pick when creating events.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('event_listing')
                            ->collection('event_listing')
                            ->multiple()
                            ->image()
                            ->reorderable()
                            ->responsiveImages()
                            ->conversion('webp')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get, ?ActivityType $record): bool => ($record?->slug ?? $get('slug')) === ActivityType::SLUG_RPG)
                    ->columnSpanFull(),
            ]);
    }
}
