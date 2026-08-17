<?php

namespace App\Filament\Admin\Resources\SentEmails;

use App\Filament\Admin\Resources\Resource;
use App\Filament\Admin\Resources\SentEmails\Pages\ListSentEmails;
use App\Filament\Admin\Resources\SentEmails\Pages\ViewSentEmail;
use App\Filament\Admin\Resources\SentEmails\Schemas\SentEmailInfolist;
use App\Filament\Admin\Resources\SentEmails\Tables\SentEmailsTable;
use App\Models\SentEmail;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SentEmailResource extends Resource
{
    protected static ?string $model = SentEmail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $navigationLabel = 'Sent emails';

    protected static ?string $modelLabel = 'sent email';

    protected static ?string $pluralModelLabel = 'sent emails';

    public static function infolist(Schema $schema): Schema
    {
        return SentEmailInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SentEmailsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSentEmails::route('/'),
            'view' => ViewSentEmail::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
