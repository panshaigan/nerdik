<?php

namespace App\Filament\Admin\Resources\EventInstances\Pages;

use App\Filament\Admin\Resources\EventInstances\EventInstanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventInstance extends CreateRecord
{
    protected static string $resource = EventInstanceResource::class;
}
