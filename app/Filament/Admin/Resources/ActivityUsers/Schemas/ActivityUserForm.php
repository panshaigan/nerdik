<?php

namespace App\Filament\Admin\Resources\ActivityUsers\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ActivityUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::activity()
                    ->required(),
                BelongsToSelect::user('user_id')
                    ->required(),
                Toggle::make('is_absent')
                    ->required(),
                BelongsToSelect::user('created_by'),
                BelongsToSelect::user('updated_by'),
                BelongsToSelect::user('deleted_by'),
            ]);
    }
}
