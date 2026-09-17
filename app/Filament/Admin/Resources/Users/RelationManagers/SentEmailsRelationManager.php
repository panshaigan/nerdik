<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Filament\Admin\Resources\SentEmails\SentEmailResource;
use App\Filament\Admin\Resources\SentEmails\Tables\SentEmailsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SentEmailsRelationManager extends RelationManager
{
    protected static string $relationship = 'sentEmails';

    protected static ?string $relatedResource = SentEmailResource::class;

    protected static ?string $title = 'Sent emails';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return SentEmailsTable::configure($table);
    }
}
