<?php

namespace App\Filament\Admin\Resources\ActivityTypeSlots\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ActivityTypeSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('slot_id')
                    ->relationship('slot', 'name')
                    ->required(),
                Select::make('activity_type_id')
                    ->relationship('activityType', 'id'),
            ]);
    }
}
