<?php

namespace App\Filament\Admin\Resources\ActivityTypes\Pages;

use App\Filament\Admin\Resources\ActivityTypes\ActivityTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateActivityType extends CreateRecord
{
    protected static string $resource = ActivityTypeResource::class;
}
