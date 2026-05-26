<?php

namespace App\Filament\Admin\Resources\ActivityWaitlistEntries\Pages;

use App\Filament\Admin\Resources\ActivityWaitlistEntries\ActivityWaitlistEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateActivityWaitlistEntry extends CreateRecord
{
    protected static string $resource = ActivityWaitlistEntryResource::class;
}
