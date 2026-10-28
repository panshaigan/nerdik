<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return UserRecipientTable::configureForUserResource($table);
    }
}
