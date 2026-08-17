<?php

namespace App\Filament\Admin\Resources\SentEmails\Pages;

use App\Filament\Admin\Resources\SentEmails\SentEmailResource;
use Filament\Resources\Pages\ListRecords;

class ListSentEmails extends ListRecords
{
    protected static string $resource = SentEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
