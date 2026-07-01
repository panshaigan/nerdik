<?php

namespace App\Filament\Admin\Resources\ActivityWaitlistEntries\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityWaitlistEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::make('activity_id', 'activity')
                    ->required(),
                BelongsToSelect::user('user_id')
                    ->required(),
                TextInput::make('position')
                    ->numeric(),
            ]);
    }
}
