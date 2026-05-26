<?php

namespace App\Filament\Admin\Resources\ActivityUsers\Pages;

use App\Filament\Admin\Resources\ActivityUsers\ActivityUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateActivityUser extends CreateRecord
{
    protected static string $resource = ActivityUserResource::class;
}
