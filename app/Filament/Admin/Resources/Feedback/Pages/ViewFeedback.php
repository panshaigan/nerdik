<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Feedback\Pages;

use App\Filament\Admin\Resources\Feedback\FeedbackResource;
use App\Models\Feedback;
use App\Models\User;
use App\Services\Feedback\FeedbackReplyService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ViewFeedback extends ViewRecord
{
    protected static string $resource = FeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label(__('feedback.admin.reply'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => ! $this->getRecord()->hasReply())
                ->schema([
                    Select::make('template')
                        ->label(__('feedback.admin.template'))
                        ->options($this->templateOptions())
                        ->placeholder(__('feedback.admin.template_placeholder'))
                        ->live()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state === null || $state === '') {
                                return;
                            }

                            $set('admin_reply', (string) __('feedback.templates.'.$state));
                        }),
                    Textarea::make('admin_reply')
                        ->label(__('feedback.admin.reply_body'))
                        ->required()
                        ->rows(8)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data, FeedbackReplyService $replies): void {
                    /** @var Feedback $record */
                    $record = $this->getRecord();
                    $admin = Auth::user();

                    if (! $admin instanceof User) {
                        return;
                    }

                    try {
                        $replies->reply($record, $admin, (string) ($data['admin_reply'] ?? ''));
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title((string) collect($exception->errors())->flatten()->first())
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title(__('feedback.admin.reply_sent'))
                        ->send();

                    $this->refreshRecord();
                }),
            Action::make('resolve')
                ->label(__('feedback.admin.resolve'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->isOpen())
                ->requiresConfirmation()
                ->action(function (FeedbackReplyService $replies): void {
                    /** @var Feedback $record */
                    $record = $this->getRecord();
                    $admin = Auth::user();

                    if (! $admin instanceof User) {
                        return;
                    }

                    $replies->resolve($record, $admin);

                    Notification::make()
                        ->success()
                        ->title(__('feedback.admin.resolved'))
                        ->send();

                    $this->refreshRecord();
                }),
            Action::make('reopen')
                ->label(__('feedback.admin.reopen'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn (): bool => $this->getRecord()->isResolved())
                ->requiresConfirmation()
                ->action(function (FeedbackReplyService $replies): void {
                    /** @var Feedback $record */
                    $record = $this->getRecord();

                    $replies->reopen($record);

                    Notification::make()
                        ->success()
                        ->title(__('feedback.admin.reopened'))
                        ->send();

                    $this->refreshRecord();
                }),
        ];
    }

    private function refreshRecord(): void
    {
        $this->getRecord()->refresh()->load(['user', 'repliedBy', 'resolvedBy']);
    }

    /**
     * @return array<string, string>
     */
    private function templateOptions(): array
    {
        /** @var list<string> $keys */
        $keys = config('feedback.reply_templates', []);

        $options = [];

        foreach ($keys as $key) {
            $options[$key] = (string) __('feedback.template_labels.'.$key);
        }

        return $options;
    }
}
