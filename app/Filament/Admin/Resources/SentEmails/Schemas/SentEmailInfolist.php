<?php

namespace App\Filament\Admin\Resources\SentEmails\Schemas;

use App\Enums\SentEmailKind;
use App\Models\SentEmail;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SentEmailInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->schema([
                        TextEntry::make('sent_at')
                            ->dateTime(),
                        TextEntry::make('kind')
                            ->badge()
                            ->formatStateUsing(fn (SentEmailKind $state): string => $state->label()),
                        TextEntry::make('subject')
                            ->columnSpanFull(),
                        TextEntry::make('recipient_email')
                            ->label('Recipient')
                            ->formatStateUsing(function (SentEmail $record): string {
                                $name = $record->recipientUser?->displayName();

                                return $name !== null && $name !== ''
                                    ? "{$name} <{$record->recipient_email}>"
                                    : $record->recipient_email;
                            }),
                        TextEntry::make('from_email')
                            ->label('From')
                            ->formatStateUsing(function (SentEmail $record): string {
                                $fromEmail = $record->from_email ?? '';
                                $fromName = $record->from_name;

                                return filled($fromName)
                                    ? "{$fromName} <{$fromEmail}>"
                                    : $fromEmail;
                            }),
                        TextEntry::make('related_type')
                            ->label('Related')
                            ->formatStateUsing(fn (SentEmail $record): string => $record->relatedLabel() ?? '—'),
                        TextEntry::make('source_class')
                            ->label('Source')
                            ->copyable(),
                        TextEntry::make('locale'),
                        TextEntry::make('mailer'),
                        TextEntry::make('provider_message_id')
                            ->label('Message ID')
                            ->placeholder('—'),
                        TextEntry::make('cc')
                            ->formatStateUsing(fn (?array $state): string => $state === null || $state === [] ? '—' : implode(', ', $state)),
                        TextEntry::make('bcc')
                            ->formatStateUsing(fn (?array $state): string => $state === null || $state === [] ? '—' : implode(', ', $state)),
                    ])
                    ->columns(2),
                Section::make('HTML')
                    ->schema([
                        ViewEntry::make('html_preview')
                            ->hiddenLabel()
                            ->view('filament.admin.resources.sent-emails.html-preview'),
                    ])
                    ->visible(fn (SentEmail $record): bool => filled($record->html_path)),
                Section::make('Plain text')
                    ->schema([
                        TextEntry::make('text_body')
                            ->hiddenLabel()
                            ->state(fn (SentEmail $record): string => $record->textBody() ?? '')
                            ->extraAttributes(['class' => 'whitespace-pre-wrap font-mono text-sm'])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (SentEmail $record): bool => filled($record->text_path)),
            ]);
    }
}
