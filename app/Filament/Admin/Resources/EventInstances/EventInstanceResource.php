<?php

namespace App\Filament\Admin\Resources\EventInstances;

use App\Filament\Admin\Resources\EventInstances\Pages\CreateEventInstance;
use App\Filament\Admin\Resources\EventInstances\Pages\EditEventInstance;
use App\Filament\Admin\Resources\EventInstances\Pages\ListEventInstances;
use App\Filament\Admin\Resources\EventInstances\Schemas\EventInstanceForm;
use App\Filament\Admin\Resources\EventInstances\Tables\EventInstancesTable;
use App\Models\EventInstance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventInstanceResource extends Resource
{
    protected static ?string $model = EventInstance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return EventInstanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventInstancesTable::configure($table);
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
            'index' => ListEventInstances::route('/'),
            'create' => CreateEventInstance::route('/create'),
            'edit' => EditEventInstance::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
