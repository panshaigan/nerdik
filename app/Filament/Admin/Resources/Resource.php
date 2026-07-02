<?php

namespace App\Filament\Admin\Resources;

use App\Support\Filament\ConfigureFilamentDisplay;
use Filament\Resources\Resource as FilamentResource;
use Filament\Tables\Table;

abstract class Resource extends FilamentResource
{
    public static function configureTable(Table $table): void
    {
        parent::configureTable($table);

        ConfigureFilamentDisplay::normalizeSelectFilterAttributes($table);
    }
}
