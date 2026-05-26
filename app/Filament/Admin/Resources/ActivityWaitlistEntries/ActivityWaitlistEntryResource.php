<?php

namespace App\Filament\Admin\Resources\ActivityWaitlistEntries;

use App\Filament\Admin\Resources\ActivityWaitlistEntries\Pages\CreateActivityWaitlistEntry;
use App\Filament\Admin\Resources\ActivityWaitlistEntries\Pages\EditActivityWaitlistEntry;
use App\Filament\Admin\Resources\ActivityWaitlistEntries\Pages\ListActivityWaitlistEntries;
use App\Filament\Admin\Resources\ActivityWaitlistEntries\Schemas\ActivityWaitlistEntryForm;
use App\Filament\Admin\Resources\ActivityWaitlistEntries\Tables\ActivityWaitlistEntriesTable;
use App\Models\ActivityWaitlistEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ActivityWaitlistEntryResource extends Resource
{
    protected static ?string $model = ActivityWaitlistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ActivityWaitlistEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityWaitlistEntriesTable::configure($table);
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
            'index' => ListActivityWaitlistEntries::route('/'),
            'create' => CreateActivityWaitlistEntry::route('/create'),
            'edit' => EditActivityWaitlistEntry::route('/{record}/edit'),
        ];
    }
}
