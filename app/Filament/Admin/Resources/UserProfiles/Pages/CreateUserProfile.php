<?php

namespace App\Filament\Admin\Resources\UserProfiles\Pages;

use App\Filament\Admin\Resources\UserProfiles\UserProfileResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserProfile extends CreateRecord
{
    protected static string $resource = UserProfileResource::class;
}
