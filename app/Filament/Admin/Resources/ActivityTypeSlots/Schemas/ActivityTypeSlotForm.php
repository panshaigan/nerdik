<?php

namespace App\Filament\Admin\Resources\ActivityTypeSlots\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Schemas\Schema;

class ActivityTypeSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::slot()
                    ->required(),
                BelongsToSelect::activityType('activity_type_id'),
            ]);
    }
}
