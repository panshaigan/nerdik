<?php

namespace App\Filament\Admin\Resources\ActivityUsers;

use App\Filament\Admin\Resources\ActivityUsers\Pages\CreateActivityUser;
use App\Filament\Admin\Resources\ActivityUsers\Pages\EditActivityUser;
use App\Filament\Admin\Resources\ActivityUsers\Pages\ListActivityUsers;
use App\Filament\Admin\Resources\ActivityUsers\Schemas\ActivityUserForm;
use App\Filament\Admin\Resources\ActivityUsers\Tables\ActivityUsersTable;
use App\Models\ActivityUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ActivityUserResource extends Resource
{
    protected static ?string $model = ActivityUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ActivityUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityUsers::route('/'),
            'create' => CreateActivityUser::route('/create'),
            'edit' => EditActivityUser::route('/{record}/edit'),
        ];
    }
}
