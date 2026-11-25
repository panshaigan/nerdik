<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PageAliases\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageAliasForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('alias')
                    ->required()
                    ->maxLength(50),
                TextInput::make('target_path')
                    ->required()
                    ->maxLength(2048),
            ]);
    }
}
