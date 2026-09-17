<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Feedback\Schemas;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\Feedback;
use App\Support\RichText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeedbackInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Submitted')
                            ->dateTime(),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn (FeedbackType $state): string => $state->label()),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (FeedbackStatus $state): string => $state->label())
                            ->color(fn (FeedbackStatus $state): string => match ($state) {
                                FeedbackStatus::Open => 'warning',
                                FeedbackStatus::Resolved => 'success',
                            }),
                        TextEntry::make('subject')
                            ->columnSpanFull(),
                        TextEntry::make('reporter')
                            ->label('Reporter')
                            ->state(function (Feedback $record): string {
                                $email = $record->reporterEmail() ?? '—';
                                $name = $record->user?->displayName();

                                return $name !== null && $name !== ''
                                    ? "{$name} <{$email}>"
                                    : $email;
                            }),
                        TextEntry::make('page_url')
                            ->label('Page URL')
                            ->url(fn (Feedback $record): ?string => $record->page_url)
                            ->openUrlInNewTab()
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('locale'),
                        TextEntry::make('user_agent')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Message')
                    ->schema([
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->prose()
                            ->html()
                            ->formatStateUsing(fn (?string $state): string => RichText::html($state)->toHtml())
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Admin reply')
                    ->schema([
                        TextEntry::make('admin_reply')
                            ->hiddenLabel()
                            ->prose()
                            ->columnSpanFull(),
                        TextEntry::make('replied_at')
                            ->dateTime(),
                        TextEntry::make('repliedBy.display_name')
                            ->label('Replied by')
                            ->state(fn (Feedback $record): ?string => $record->repliedBy?->displayName()),
                    ])
                    ->columns(2)
                    ->visible(fn (Feedback $record): bool => $record->hasReply()),
                Section::make('Resolution')
                    ->schema([
                        TextEntry::make('resolved_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('resolvedBy.display_name')
                            ->label('Resolved by')
                            ->state(fn (Feedback $record): ?string => $record->resolvedBy?->displayName())
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->visible(fn (Feedback $record): bool => $record->isResolved()),
            ]);
    }
}
