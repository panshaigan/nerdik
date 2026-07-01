<?php

namespace App\Filament\Admin\Resources\Events\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name'),
                BelongsToSelect::make('organization_id', 'organization'),
                Toggle::make('is_public')
                    ->required(),
                TextInput::make('logo_path'),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->required(),
                Textarea::make('cancel_reason')
                    ->columnSpanFull(),
                DateTimePicker::make('cancelled_at'),
                BelongsToSelect::user('cancelled_by', 'canceller'),
                BelongsToSelect::user('created_by'),
                BelongsToSelect::user('updated_by'),
                BelongsToSelect::user('deleted_by'),
                TextInput::make('search_vector'),
            ]);
    }
}
