<?php

namespace App\Filament\Admin\Resources\ActivityTypeSlots;

use App\Filament\Admin\Resources\ActivityTypeSlots\Pages\CreateActivityTypeSlot;
use App\Filament\Admin\Resources\ActivityTypeSlots\Pages\EditActivityTypeSlot;
use App\Filament\Admin\Resources\ActivityTypeSlots\Pages\ListActivityTypeSlots;
use App\Filament\Admin\Resources\ActivityTypeSlots\Schemas\ActivityTypeSlotForm;
use App\Filament\Admin\Resources\ActivityTypeSlots\Tables\ActivityTypeSlotsTable;
use App\Models\ActivityTypeSlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ActivityTypeSlotResource extends Resource
{
    protected static ?string $model = ActivityTypeSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ActivityTypeSlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityTypeSlotsTable::configure($table);
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
            'index' => ListActivityTypeSlots::route('/'),
            'create' => CreateActivityTypeSlot::route('/create'),
            'edit' => EditActivityTypeSlot::route('/{record}/edit'),
        ];
    }
}
