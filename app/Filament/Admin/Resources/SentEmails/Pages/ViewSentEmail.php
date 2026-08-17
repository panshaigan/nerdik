<?php

namespace App\Filament\Admin\Resources\SentEmails\Pages;

use App\Filament\Admin\Resources\SentEmails\SentEmailResource;
use App\Models\SentEmail;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewSentEmail extends ViewRecord
{
    protected static string $resource = SentEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadHtml')
                ->label('Download HTML')
                ->visible(fn (): bool => filled($this->getRecord()->html_path))
                ->action(function (): StreamedResponse {
                    /** @var SentEmail $record */
                    $record = $this->getRecord();

                    return Storage::disk('email_logs')->download(
                        (string) $record->html_path,
                        $record->uuid.'.html',
                    );
                }),
            Action::make('downloadText')
                ->label('Download text')
                ->visible(fn (): bool => filled($this->getRecord()->text_path))
                ->action(function (): StreamedResponse {
                    /** @var SentEmail $record */
                    $record = $this->getRecord();

                    return Storage::disk('email_logs')->download(
                        (string) $record->text_path,
                        $record->uuid.'.txt',
                    );
                }),
        ];
    }
}
