<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Enums\FeedbackStatus;
use App\Enums\FeedbackType;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackReceivedNotification;
use App\Support\RichText;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class FeedbackSubmissionService
{
    /**
     * @param  array{
     *     type: string,
     *     subject: string,
     *     body: string,
     *     email?: string|null,
     *     page_url?: string|null,
     *     user_agent?: string|null,
     * }  $data
     */
    public function submit(array $data, ?User $user = null): Feedback
    {
        $body = RichText::sanitize($data['body'] ?? null);

        if ($body === null) {
            throw ValidationException::withMessages([
                'body' => [__('validation.required', ['attribute' => 'body'])],
            ]);
        }

        $type = FeedbackType::from($data['type']);
        $email = $user?->email ?? (isset($data['email']) ? trim((string) $data['email']) : null);

        if ($user === null && ($email === null || $email === '')) {
            throw ValidationException::withMessages([
                'email' => [__('validation.required', ['attribute' => 'email'])],
            ]);
        }

        $feedback = Feedback::query()->create([
            'type' => $type,
            'subject' => trim($data['subject']),
            'body' => $body,
            'email' => $email,
            'user_id' => $user?->id,
            'status' => FeedbackStatus::Open,
            'page_url' => $this->nullableTrim($data['page_url'] ?? null),
            'locale' => app()->getLocale(),
            'user_agent' => $this->nullableTrim($data['user_agent'] ?? null),
        ]);

        $admins = User::query()->where('is_admin', true)->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new FeedbackReceivedNotification($feedback));
        }

        return $feedback;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
