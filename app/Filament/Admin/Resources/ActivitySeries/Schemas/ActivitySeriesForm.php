<?php

namespace App\Filament\Admin\Resources\ActivitySeries\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivitySeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
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
