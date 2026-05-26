<?php

namespace App\Filament\Admin\Resources\EventEnrollmentWindows;

use App\Filament\Admin\Resources\EventEnrollmentWindows\Pages\CreateEventEnrollmentWindow;
use App\Filament\Admin\Resources\EventEnrollmentWindows\Pages\EditEventEnrollmentWindow;
use App\Filament\Admin\Resources\EventEnrollmentWindows\Pages\ListEventEnrollmentWindows;
use App\Filament\Admin\Resources\EventEnrollmentWindows\Schemas\EventEnrollmentWindowForm;
use App\Filament\Admin\Resources\EventEnrollmentWindows\Tables\EventEnrollmentWindowsTable;
use App\Models\EventEnrollmentWindow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EventEnrollmentWindowResource extends Resource
{
    protected static ?string $model = EventEnrollmentWindow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return EventEnrollmentWindowForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventEnrollmentWindowsTable::configure($table);
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
            'index' => ListEventEnrollmentWindows::route('/'),
            'create' => CreateEventEnrollmentWindow::route('/create'),
            'edit' => EditEventEnrollmentWindow::route('/{record}/edit'),
        ];
    }
}
