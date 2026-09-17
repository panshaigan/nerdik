<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Feedback\Pages;

use App\Filament\Admin\Resources\Feedback\FeedbackResource;
use Filament\Resources\Pages\ListRecords;

class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
