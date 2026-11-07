<?php

declare(strict_types=1);

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\TextColumn;

final class IdColumn
{
    public static function make(): TextColumn
    {
        return TextColumn::make('id')
            ->label('ID')
            ->sortable();
    }
}
